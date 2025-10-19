"""
ECS Service Scheduler Lambda Function

Scales ECS services up/down based on EventBridge schedule.
Used to reduce costs by running services only during transit operating hours.

Environment Variables:
    CLUSTER_NAME: ECS cluster name (e.g., "mind-the-wait-prod")

Event Payload:
    {
        "action": "scale_up" | "scale_down"
    }
"""

import boto3
import os
from datetime import datetime

ecs = boto3.client('ecs')
ssm = boto3.client('ssm')
cloudwatch = boto3.client('cloudwatch')

CLUSTER_NAME = os.environ['CLUSTER_NAME']

# Service scaling configuration
# Format: {service_name: {on: desired_count_when_on, off: desired_count_when_off}}
SERVICES = {
    'php': {'on': 1, 'off': 0},
    'pyparser': {'on': 1, 'off': 0},
    'scheduler': {'on': 1, 'off': 0},
}


def check_override():
    """
    Check if manual override is enabled via SSM Parameter Store.

    Returns:
        bool: True if override is enabled, False otherwise
    """
    try:
        response = ssm.get_parameter(Name='/mind-the-wait/scheduler/override')
        return response['Parameter']['Value'].lower() == 'true'
    except ssm.exceptions.ParameterNotFound:
        return False
    except Exception as e:
        print(f"Error checking override parameter: {e}")
        return False


def publish_metric(metric_name, value):
    """
    Publish custom CloudWatch metric for monitoring.

    Args:
        metric_name (str): Name of the metric
        value (float): Metric value
    """
    try:
        cloudwatch.put_metric_data(
            Namespace='MindTheWait/Scheduler',
            MetricData=[{
                'MetricName': metric_name,
                'Value': value,
                'Unit': 'Count',
                'Timestamp': datetime.utcnow()
            }]
        )
    except Exception as e:
        print(f"Error publishing metric {metric_name}: {e}")


def lambda_handler(event, context):
    """
    Main Lambda handler function.

    Args:
        event (dict): Event payload containing 'action' key
        context (object): Lambda context object

    Returns:
        dict: Response with statusCode and body
    """
    action = event.get('action')

    if action not in ['scale_up', 'scale_down']:
        error_msg = f"Invalid action: {action}. Must be 'scale_up' or 'scale_down'"
        print(error_msg)
        publish_metric('InvalidAction', 1)
        return {'statusCode': 400, 'body': error_msg}

    # Check for manual override
    if check_override():
        msg = "Manual override enabled - skipping scheduled action"
        print(msg)
        publish_metric('OverrideActive', 1)
        return {'statusCode': 200, 'body': msg}

    print(f"Executing {action} for cluster {CLUSTER_NAME}")

    success_count = 0
    failure_count = 0

    for service_name, counts in SERVICES.items():
        desired_count = counts['on'] if action == 'scale_up' else counts['off']
        full_service_name = f"{CLUSTER_NAME.rsplit('-', 1)[0]}-{service_name}"

        try:
            print(f"{action}: Setting {full_service_name} to {desired_count} tasks")

            ecs.update_service(
                cluster=CLUSTER_NAME,
                service=full_service_name,
                desiredCount=desired_count
            )

            success_count += 1

        except ecs.exceptions.ServiceNotFoundException:
            print(f"Service not found: {full_service_name} - skipping")
        except Exception as e:
            print(f"Error updating {full_service_name}: {e}")
            failure_count += 1

    # Publish metrics
    publish_metric(f'{action.title()}Success', success_count)
    if failure_count > 0:
        publish_metric(f'{action.title()}Failure', failure_count)

    result_msg = f"Successfully executed {action}: {success_count} services updated, {failure_count} failures"
    print(result_msg)

    return {
        'statusCode': 200 if failure_count == 0 else 207,
        'body': result_msg
    }
