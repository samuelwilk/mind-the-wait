#!/bin/bash
#
# AWS Resource Audit Script
#
# Audits all running AWS resources for mind-the-wait project
# to identify cost drivers and verify configuration matches terraform.
#
# Usage:
#   ./scripts/audit-aws-resources.sh
#
# Requirements:
#   - AWS CLI configured with mind-the-wait profile
#   - jq installed for JSON parsing

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PROFILE="mind-the-wait"

echo "=========================================="
echo "Mind-the-Wait AWS Resource Audit"
echo "=========================================="
echo ""

# Check prerequisites
if ! command -v aws &> /dev/null; then
    echo -e "${RED}Error: AWS CLI not installed${NC}"
    exit 1
fi

if ! command -v jq &> /dev/null; then
    echo -e "${YELLOW}Warning: jq not installed. Output will be less formatted.${NC}"
    JQ_INSTALLED=false
else
    JQ_INSTALLED=true
fi

# Function to print section header
print_section() {
    echo ""
    echo -e "${GREEN}=== $1 ===${NC}"
    echo ""
}

# Function to count resources
count_resources() {
    local count=$1
    local resource=$2
    echo -e "${YELLOW}Found: $count $resource${NC}"
}

# 1. ECS Tasks
print_section "ECS Fargate Tasks"
echo "Listing all running tasks..."

TASKS=$(aws ecs list-tasks \
    --cluster mind-the-wait-prod \
    --desired-status RUNNING \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.taskArns | length' || echo "0")

count_resources "$TASKS" "running tasks"

if [ "$TASKS" -gt 0 ]; then
    echo ""
    echo "Task breakdown by service:"
    aws ecs list-services \
        --cluster mind-the-wait-prod \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.serviceArns[]' | while read -r service; do
            service_name=$(basename "$service")
            task_count=$(aws ecs describe-services \
                --cluster mind-the-wait-prod \
                --services "$service" \
                --profile "$PROFILE" \
                2>/dev/null | jq -r '.services[0].runningCount')
            echo "  - $service_name: $task_count tasks"
        done
fi

# 2. RDS Instances
print_section "RDS Instances"
echo "Listing all RDS instances..."

RDS_COUNT=$(aws rds describe-db-instances \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.DBInstances | length' || echo "0")

count_resources "$RDS_COUNT" "RDS instances"

if [ "$RDS_COUNT" -gt 0 ]; then
    echo ""
    aws rds describe-db-instances \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.DBInstances[] | "  - \(.DBInstanceIdentifier): \(.DBInstanceClass) (\(.DBInstanceStatus))"'
fi

# 3. ElastiCache Clusters
print_section "ElastiCache Redis Clusters"
echo "Listing all ElastiCache clusters..."

REDIS_COUNT=$(aws elasticache describe-cache-clusters \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.CacheClusters | length' || echo "0")

count_resources "$REDIS_COUNT" "Redis clusters"

if [ "$REDIS_COUNT" -gt 0 ]; then
    echo ""
    aws elasticache describe-cache-clusters \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.CacheClusters[] | "  - \(.CacheClusterId): \(.CacheNodeType) (\(.CacheClusterStatus))"'
fi

# 4. Load Balancers
print_section "Application Load Balancers"
echo "Listing all ALBs..."

ALB_COUNT=$(aws elbv2 describe-load-balancers \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.LoadBalancers | length' || echo "0")

count_resources "$ALB_COUNT" "load balancers"

if [ "$ALB_COUNT" -gt 0 ]; then
    echo ""
    aws elbv2 describe-load-balancers \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.LoadBalancers[] | "  - \(.LoadBalancerName): \(.State.Code)"'
fi

# 5. NAT Gateways (should be 0)
print_section "NAT Gateways"
echo "Checking for NAT Gateways (should be 0 for cost optimization)..."

NAT_COUNT=$(aws ec2 describe-nat-gateways \
    --filter "Name=state,Values=available" \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.NatGateways | length' || echo "0")

if [ "$NAT_COUNT" -eq 0 ]; then
    echo -e "${GREEN}✓ No NAT Gateways found (good - saves ~\$32/month)${NC}"
else
    echo -e "${RED}⚠ Found $NAT_COUNT NAT Gateway(s) - consider removing to save costs${NC}"
    aws ec2 describe-nat-gateways \
        --filter "Name=state,Values=available" \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.NatGateways[] | "  - \(.NatGatewayId): \(.State)"'
fi

# 6. EBS Volumes (orphaned)
print_section "EBS Volumes"
echo "Checking for orphaned EBS volumes..."

ORPHANED_VOLUMES=$(aws ec2 describe-volumes \
    --filters "Name=status,Values=available" \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.Volumes | length' || echo "0")

if [ "$ORPHANED_VOLUMES" -eq 0 ]; then
    echo -e "${GREEN}✓ No orphaned volumes found${NC}"
else
    echo -e "${YELLOW}Found $ORPHANED_VOLUMES orphaned volume(s) - consider deleting${NC}"
    aws ec2 describe-volumes \
        --filters "Name=status,Values=available" \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.Volumes[] | "  - \(.VolumeId): \(.Size)GB (\(.VolumeType))"'
fi

# 7. CloudWatch Log Groups
print_section "CloudWatch Log Groups"
echo "Checking log retention settings..."

LOG_GROUPS=$(aws logs describe-log-groups \
    --log-group-name-prefix "/ecs/mind-the-wait" \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.logGroups | length' || echo "0")

count_resources "$LOG_GROUPS" "log groups"

if [ "$LOG_GROUPS" -gt 0 ]; then
    echo ""
    aws logs describe-log-groups \
        --log-group-name-prefix "/ecs/mind-the-wait" \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.logGroups[] | "  - \(.logGroupName): \(.retentionInDays // "Never expire") days retention"'
fi

# 8. ECR Repositories
print_section "ECR Repositories"
echo "Checking ECR image storage..."

ECR_COUNT=$(aws ecr describe-repositories \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '.repositories | length' || echo "0")

count_resources "$ECR_COUNT" "ECR repositories"

if [ "$ECR_COUNT" -gt 0 ]; then
    echo ""
    total_size=0
    aws ecr describe-repositories \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.repositories[].repositoryName' | while read -r repo; do
            image_count=$(aws ecr list-images \
                --repository-name "$repo" \
                --profile "$PROFILE" \
                2>/dev/null | jq -r '.imageIds | length')
            echo "  - $repo: $image_count images"
        done
fi

# Summary
print_section "Summary & Recommendations"

echo "Cost Optimization Checklist:"
echo ""
echo "✓ = Optimized | ⚠ = Needs attention | ✗ = High cost"
echo ""

# Check if using Spot
SPOT_COUNT=$(aws ecs describe-tasks \
    --cluster mind-the-wait-prod \
    --profile "$PROFILE" \
    2>/dev/null | jq -r '[.tasks[] | select(.capacityProviderName == "FARGATE_SPOT")] | length' || echo "0")

if [ "$SPOT_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Using Fargate Spot ($SPOT_COUNT tasks) - saving 70%"
else
    echo -e "${YELLOW}⚠${NC} Not using Fargate Spot - consider switching to save 70%"
fi

# Check NAT Gateway
if [ "$NAT_COUNT" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No NAT Gateway - saving ~\$32/month"
else
    echo -e "${RED}✗${NC} NAT Gateway detected - costs ~\$32/month"
fi

# Check log retention
if [ "$LOG_GROUPS" -gt 0 ]; then
    HIGH_RETENTION=$(aws logs describe-log-groups \
        --log-group-name-prefix "/ecs/mind-the-wait" \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '[.logGroups[] | select(.retentionInDays > 7)] | length' || echo "0")

    if [ "$HIGH_RETENTION" -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Log retention ≤7 days - optimized"
    else
        echo -e "${YELLOW}⚠${NC} Some logs retained >7 days - consider reducing to 3-7 days"
    fi
fi

# Check RDS Multi-AZ
if [ "$RDS_COUNT" -gt 0 ]; then
    MULTI_AZ=$(aws rds describe-db-instances \
        --profile "$PROFILE" \
        2>/dev/null | jq -r '.DBInstances[0].MultiAZ')

    if [ "$MULTI_AZ" = "false" ]; then
        echo -e "${GREEN}✓${NC} RDS single-AZ (development) - saving 50%"
    else
        echo -e "${YELLOW}⚠${NC} RDS Multi-AZ enabled - consider disabling for development"
    fi
fi

echo ""
echo -e "${GREEN}Audit complete!${NC}"
echo ""
echo "Next steps:"
echo "  1. Review findings above"
echo "  2. Check AWS Cost Explorer for detailed cost breakdown"
echo "  3. Implement cost optimizations from docs/infrastructure/AWS_COST_OPTIMIZATION.md"
echo ""
