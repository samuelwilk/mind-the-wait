"""
RDS Instance Scheduler Lambda Function

Starts and stops RDS instances based on EventBridge schedule.
Used to reduce costs by stopping database during off-hours.

Environment Variables:
    DB_INSTANCE_ID: RDS instance identifier (e.g., "mind-the-wait-prod")

Event Payload:
    {
        "action": "start" | "stop"
    }

Notes:
    - RDS stop is temporary (max 7 days), then AWS auto-starts
    - Stop/start takes 2-3 minutes
    - Ensure start happens BEFORE ECS services scale up
"""

import boto3
import os
from datetime import datetime

rds = boto3.client('rds')
ssm = boto3.client('ssm')
cloudwatch = boto3.client('cloudwatch')

DB_INSTANCE_ID = os.environ['DB_INSTANCE_ID']


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
            Namespace='MindTheWait/RDSScheduler',
            MetricData=[{
                'MetricName': metric_name,
                'Value': value,
                'Unit': 'Count',
                'Timestamp': datetime.utcnow()
            }]
        )
    except Exception as e:
        print(f"Error publishing metric {metric_name}: {e}")


def get_db_status():
    """
    Get current RDS instance status.

    Returns:
        str: Current status (e.g., "available", "stopped", "stopping", "starting")
    """
    try:
        response = rds.describe_db_instances(DBInstanceIdentifier=DB_INSTANCE_ID)
        return response['DBInstances'][0]['DBInstanceStatus']
    except Exception as e:
        print(f"Error getting DB status: {e}")
        return None


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

    if action not in ['start', 'stop']:
        error_msg = f"Invalid action: {action}. Must be 'start' or 'stop'"
        print(error_msg)
        publish_metric('InvalidAction', 1)
        return {'statusCode': 400, 'body': error_msg}

    # Check for manual override
    if check_override():
        msg = "Manual override enabled - skipping scheduled action"
        print(msg)
        publish_metric('OverrideActive', 1)
        return {'statusCode': 200, 'body': msg}

    # Get current DB status
    current_status = get_db_status()
    if not current_status:
        error_msg = f"Failed to get status for DB instance {DB_INSTANCE_ID}"
        print(error_msg)
        publish_metric(f'{action.title()}Failure', 1)
        return {'statusCode': 500, 'body': error_msg}

    print(f"Current DB status: {current_status}")

    # Skip if already in desired state
    if action == 'stop' and current_status in ['stopped', 'stopping']:
        msg = f"DB instance {DB_INSTANCE_ID} is already {current_status}"
        print(msg)
        publish_metric('AlreadyInDesiredState', 1)
        return {'statusCode': 200, 'body': msg}

    if action == 'start' and current_status in ['available', 'starting']:
        msg = f"DB instance {DB_INSTANCE_ID} is already {current_status}"
        print(msg)
        publish_metric('AlreadyInDesiredState', 1)
        return {'statusCode': 200, 'body': msg}

    # Perform action
    try:
        if action == 'stop':
            print(f"Stopping RDS instance {DB_INSTANCE_ID}")
            rds.stop_db_instance(DBInstanceIdentifier=DB_INSTANCE_ID)
            publish_metric('StopSuccess', 1)
            msg = f"Successfully initiated stop for {DB_INSTANCE_ID}"

        elif action == 'start':
            print(f"Starting RDS instance {DB_INSTANCE_ID}")
            rds.start_db_instance(DBInstanceIdentifier=DB_INSTANCE_ID)
            publish_metric('StartSuccess', 1)
            msg = f"Successfully initiated start for {DB_INSTANCE_ID}"

        print(msg)
        return {'statusCode': 200, 'body': msg}

    except rds.exceptions.InvalidDBInstanceStateFault as e:
        error_msg = f"Invalid DB state for {action}: {e}"
        print(error_msg)
        publish_metric(f'{action.title()}InvalidState', 1)
        return {'statusCode': 400, 'body': error_msg}

    except Exception as e:
        error_msg = f"Error executing {action} on {DB_INSTANCE_ID}: {e}"
        print(error_msg)
        publish_metric(f'{action.title()}Failure', 1)
        return {'statusCode': 500, 'body': error_msg}
