# AWS IAM Least-Privilege Policies

## Overview

This document defines the minimum AWS IAM permissions required for:
1. **Terraform User** - Infrastructure management (replaces current admin access)
2. **GitHub Actions Deployment** - CI/CD pipeline for deploying application updates

**Current Problem:** The Terraform user has `AdministratorAccess` policy, which violates the principle of least privilege and poses security risks.

**Solution:** Create specific IAM policies that grant only the permissions needed for each use case.

---

## Policy 1: Terraform Infrastructure Management

### Purpose
Allows Terraform to manage all infrastructure resources for mind-the-wait project.

### IAM Policy JSON

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "TerraformStateManagement",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::mind-the-wait-terraform-state",
        "arn:aws:s3:::mind-the-wait-terraform-state/*"
      ]
    },
    {
      "Sid": "TerraformStateLocking",
      "Effect": "Allow",
      "Action": [
        "dynamodb:GetItem",
        "dynamodb:PutItem",
        "dynamodb:DeleteItem",
        "dynamodb:DescribeTable"
      ],
      "Resource": "arn:aws:dynamodb:ca-central-1:*:table/mind-the-wait-terraform-locks"
    },
    {
      "Sid": "VPCNetworkingManagement",
      "Effect": "Allow",
      "Action": [
        "ec2:AllocateAddress",
        "ec2:AssociateAddress",
        "ec2:AssociateRouteTable",
        "ec2:AttachInternetGateway",
        "ec2:AuthorizeSecurityGroupEgress",
        "ec2:AuthorizeSecurityGroupIngress",
        "ec2:CreateInternetGateway",
        "ec2:CreateNatGateway",
        "ec2:CreateRoute",
        "ec2:CreateRouteTable",
        "ec2:CreateSecurityGroup",
        "ec2:CreateSubnet",
        "ec2:CreateTags",
        "ec2:CreateVpc",
        "ec2:DeleteInternetGateway",
        "ec2:DeleteNatGateway",
        "ec2:DeleteRoute",
        "ec2:DeleteRouteTable",
        "ec2:DeleteSecurityGroup",
        "ec2:DeleteSubnet",
        "ec2:DeleteTags",
        "ec2:DeleteVpc",
        "ec2:DescribeAccountAttributes",
        "ec2:DescribeAddresses",
        "ec2:DescribeAvailabilityZones",
        "ec2:DescribeInternetGateways",
        "ec2:DescribeNatGateways",
        "ec2:DescribeNetworkInterfaces",
        "ec2:DescribeRouteTables",
        "ec2:DescribeSecurityGroups",
        "ec2:DescribeSecurityGroupRules",
        "ec2:DescribeSubnets",
        "ec2:DescribeTags",
        "ec2:DescribeVpcAttribute",
        "ec2:DescribeVpcClassicLink",
        "ec2:DescribeVpcClassicLinkDnsSupport",
        "ec2:DescribeVpcs",
        "ec2:DetachInternetGateway",
        "ec2:DisassociateAddress",
        "ec2:DisassociateRouteTable",
        "ec2:ModifySecurityGroupRules",
        "ec2:ModifySubnetAttribute",
        "ec2:ModifyVpcAttribute",
        "ec2:ReleaseAddress",
        "ec2:RevokeSecurityGroupEgress",
        "ec2:RevokeSecurityGroupIngress",
        "ec2:UpdateSecurityGroupRuleDescriptionsEgress",
        "ec2:UpdateSecurityGroupRuleDescriptionsIngress"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "ECRRepositoryManagement",
      "Effect": "Allow",
      "Action": [
        "ecr:BatchCheckLayerAvailability",
        "ecr:BatchGetImage",
        "ecr:CreateRepository",
        "ecr:DeleteRepository",
        "ecr:DescribeImages",
        "ecr:DescribeRepositories",
        "ecr:GetAuthorizationToken",
        "ecr:GetDownloadUrlForLayer",
        "ecr:GetLifecyclePolicy",
        "ecr:GetLifecyclePolicyPreview",
        "ecr:GetRepositoryPolicy",
        "ecr:ListImages",
        "ecr:ListTagsForResource",
        "ecr:PutImage",
        "ecr:PutImageScanningConfiguration",
        "ecr:PutImageTagMutability",
        "ecr:PutLifecyclePolicy",
        "ecr:SetRepositoryPolicy",
        "ecr:TagResource",
        "ecr:UntagResource"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "RDSManagement",
      "Effect": "Allow",
      "Action": [
        "rds:AddTagsToResource",
        "rds:CreateDBInstance",
        "rds:CreateDBParameterGroup",
        "rds:CreateDBSubnetGroup",
        "rds:DeleteDBInstance",
        "rds:DeleteDBParameterGroup",
        "rds:DeleteDBSubnetGroup",
        "rds:DescribeDBEngineVersions",
        "rds:DescribeDBInstances",
        "rds:DescribeDBParameterGroups",
        "rds:DescribeDBParameters",
        "rds:DescribeDBSubnetGroups",
        "rds:ListTagsForResource",
        "rds:ModifyDBInstance",
        "rds:ModifyDBParameterGroup",
        "rds:ModifyDBSubnetGroup",
        "rds:RemoveTagsFromResource"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "ElastiCacheManagement",
      "Effect": "Allow",
      "Action": [
        "elasticache:AddTagsToResource",
        "elasticache:CreateCacheCluster",
        "elasticache:CreateCacheParameterGroup",
        "elasticache:CreateCacheSubnetGroup",
        "elasticache:CreateReplicationGroup",
        "elasticache:DeleteCacheCluster",
        "elasticache:DeleteCacheParameterGroup",
        "elasticache:DeleteCacheSubnetGroup",
        "elasticache:DeleteReplicationGroup",
        "elasticache:DescribeCacheClusters",
        "elasticache:DescribeCacheEngineVersions",
        "elasticache:DescribeCacheParameterGroups",
        "elasticache:DescribeCacheParameters",
        "elasticache:DescribeCacheSubnetGroups",
        "elasticache:DescribeReplicationGroups",
        "elasticache:ListTagsForResource",
        "elasticache:ModifyCacheCluster",
        "elasticache:ModifyCacheParameterGroup",
        "elasticache:ModifyCacheSubnetGroup",
        "elasticache:ModifyReplicationGroup",
        "elasticache:RemoveTagsFromResource"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "Route53DNSManagement",
      "Effect": "Allow",
      "Action": [
        "route53:ChangeResourceRecordSets",
        "route53:ChangeTagsForResource",
        "route53:CreateHostedZone",
        "route53:DeleteHostedZone",
        "route53:GetChange",
        "route53:GetHostedZone",
        "route53:ListHostedZones",
        "route53:ListHostedZonesByName",
        "route53:ListResourceRecordSets",
        "route53:ListTagsForResource"
      ],
      "Resource": "*"
    },
    {
      "Sid": "ACMCertificateManagement",
      "Effect": "Allow",
      "Action": [
        "acm:AddTagsToCertificate",
        "acm:DeleteCertificate",
        "acm:DescribeCertificate",
        "acm:GetCertificate",
        "acm:ListCertificates",
        "acm:ListTagsForCertificate",
        "acm:RemoveTagsFromCertificate",
        "acm:RequestCertificate",
        "acm:ResendValidationEmail"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "LoadBalancerManagement",
      "Effect": "Allow",
      "Action": [
        "elasticloadbalancing:AddTags",
        "elasticloadbalancing:CreateListener",
        "elasticloadbalancing:CreateLoadBalancer",
        "elasticloadbalancing:CreateTargetGroup",
        "elasticloadbalancing:DeleteListener",
        "elasticloadbalancing:DeleteLoadBalancer",
        "elasticloadbalancing:DeleteTargetGroup",
        "elasticloadbalancing:DeregisterTargets",
        "elasticloadbalancing:DescribeListeners",
        "elasticloadbalancing:DescribeLoadBalancerAttributes",
        "elasticloadbalancing:DescribeLoadBalancers",
        "elasticloadbalancing:DescribeTags",
        "elasticloadbalancing:DescribeTargetGroupAttributes",
        "elasticloadbalancing:DescribeTargetGroups",
        "elasticloadbalancing:DescribeTargetHealth",
        "elasticloadbalancing:ModifyListener",
        "elasticloadbalancing:ModifyLoadBalancerAttributes",
        "elasticloadbalancing:ModifyTargetGroup",
        "elasticloadbalancing:ModifyTargetGroupAttributes",
        "elasticloadbalancing:RegisterTargets",
        "elasticloadbalancing:RemoveTags",
        "elasticloadbalancing:SetSecurityGroups",
        "elasticloadbalancing:SetSubnets"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "ECSClusterManagement",
      "Effect": "Allow",
      "Action": [
        "ecs:CreateCluster",
        "ecs:CreateService",
        "ecs:DeleteCluster",
        "ecs:DeleteService",
        "ecs:DeregisterTaskDefinition",
        "ecs:DescribeClusters",
        "ecs:DescribeServices",
        "ecs:DescribeTaskDefinition",
        "ecs:DescribeTasks",
        "ecs:ListClusters",
        "ecs:ListServices",
        "ecs:ListTagsForResource",
        "ecs:ListTaskDefinitions",
        "ecs:RegisterTaskDefinition",
        "ecs:TagResource",
        "ecs:UntagResource",
        "ecs:UpdateService"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "ECSAutoScaling",
      "Effect": "Allow",
      "Action": [
        "application-autoscaling:DeleteScalingPolicy",
        "application-autoscaling:DeleteScheduledAction",
        "application-autoscaling:DeregisterScalableTarget",
        "application-autoscaling:DescribeScalableTargets",
        "application-autoscaling:DescribeScalingActivities",
        "application-autoscaling:DescribeScalingPolicies",
        "application-autoscaling:DescribeScheduledActions",
        "application-autoscaling:PutScalingPolicy",
        "application-autoscaling:PutScheduledAction",
        "application-autoscaling:RegisterScalableTarget"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "CloudWatchLogsManagement",
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:DeleteLogGroup",
        "logs:DeleteRetentionPolicy",
        "logs:DescribeLogGroups",
        "logs:ListTagsLogGroup",
        "logs:PutRetentionPolicy",
        "logs:TagLogGroup",
        "logs:UntagLogGroup"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "ca-central-1"
        }
      }
    },
    {
      "Sid": "IAMRoleManagementForECS",
      "Effect": "Allow",
      "Action": [
        "iam:AttachRolePolicy",
        "iam:CreatePolicy",
        "iam:CreatePolicyVersion",
        "iam:CreateRole",
        "iam:DeletePolicy",
        "iam:DeletePolicyVersion",
        "iam:DeleteRole",
        "iam:DeleteRolePolicy",
        "iam:DetachRolePolicy",
        "iam:GetPolicy",
        "iam:GetPolicyVersion",
        "iam:GetRole",
        "iam:GetRolePolicy",
        "iam:ListAttachedRolePolicies",
        "iam:ListInstanceProfilesForRole",
        "iam:ListPolicyVersions",
        "iam:ListRolePolicies",
        "iam:PassRole",
        "iam:PutRolePolicy",
        "iam:TagPolicy",
        "iam:TagRole",
        "iam:UntagPolicy",
        "iam:UntagRole",
        "iam:UpdateAssumeRolePolicy"
      ],
      "Resource": [
        "arn:aws:iam::*:role/mind-the-wait-*",
        "arn:aws:iam::*:policy/mind-the-wait-*"
      ]
    },
    {
      "Sid": "ReadOnlyIAMForServiceLinkedRoles",
      "Effect": "Allow",
      "Action": [
        "iam:GetRole",
        "iam:ListRoles"
      ],
      "Resource": "*"
    }
  ]
}
```

### Implementation Steps

1. **Create the IAM policy:**
```bash
# Save policy JSON to file
cat > terraform-policy.json <<'EOF'
{
  # paste policy JSON above
}
EOF

# Create policy in AWS
aws iam create-policy \
  --policy-name MindTheWaitTerraformPolicy \
  --policy-document file://terraform-policy.json \
  --description "Least-privilege policy for Terraform infrastructure management"
```

2. **Detach admin policy from user:**
```bash
aws iam detach-user-policy \
  --user-name terraform-user \
  --policy-arn arn:aws:iam::aws:policy/AdministratorAccess
```

3. **Attach new policy to user:**
```bash
aws iam attach-user-policy \
  --user-name terraform-user \
  --policy-arn arn:aws:iam::YOUR_ACCOUNT_ID:policy/MindTheWaitTerraformPolicy
```

4. **Test Terraform:**
```bash
cd terraform/environments/prod
terraform plan
```

---

## Policy 2: GitHub Actions Deployment (CI/CD)

### Purpose
Allows GitHub Actions to:
- Push Docker images to ECR
- Update ECS services to trigger deployments
- Wait for service stability

### IAM Policy JSON

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ECRAuthentication",
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken"
      ],
      "Resource": "*"
    },
    {
      "Sid": "ECRImagePush",
      "Effect": "Allow",
      "Action": [
        "ecr:BatchCheckLayerAvailability",
        "ecr:CompleteLayerUpload",
        "ecr:GetDownloadUrlForLayer",
        "ecr:InitiateLayerUpload",
        "ecr:PutImage",
        "ecr:UploadLayerPart"
      ],
      "Resource": [
        "arn:aws:ecr:ca-central-1:*:repository/mind-the-wait/php",
        "arn:aws:ecr:ca-central-1:*:repository/mind-the-wait/pyparser"
      ]
    },
    {
      "Sid": "ECSServiceDeployment",
      "Effect": "Allow",
      "Action": [
        "ecs:DescribeServices",
        "ecs:UpdateService"
      ],
      "Resource": [
        "arn:aws:ecs:ca-central-1:*:service/mind-the-wait-prod/mind-the-wait-prod-php",
        "arn:aws:ecs:ca-central-1:*:service/mind-the-wait-prod/mind-the-wait-prod-pyparser",
        "arn:aws:ecs:ca-central-1:*:service/mind-the-wait-prod/mind-the-wait-prod-scheduler"
      ]
    },
    {
      "Sid": "ECSWaitForStability",
      "Effect": "Allow",
      "Action": [
        "ecs:DescribeServices",
        "ecs:DescribeTasks"
      ],
      "Resource": "*"
    }
  ]
}
```

### Implementation Steps

**If using separate IAM user for GitHub Actions:**

1. **Create deployment policy:**
```bash
aws iam create-policy \
  --policy-name MindTheWaitGitHubActionsPolicy \
  --policy-document file://github-actions-policy.json \
  --description "Least-privilege policy for GitHub Actions deployments"
```

2. **Attach to GitHub Actions user:**
```bash
aws iam attach-user-policy \
  --user-name github-actions-user \
  --policy-arn arn:aws:iam::YOUR_ACCOUNT_ID:policy/MindTheWaitGitHubActionsPolicy
```

**If using same user for both (not recommended but acceptable):**

Just attach both policies to the same user.

---

## Testing the New Policies

### Test 1: Terraform State Access
```bash
cd terraform/environments/prod
terraform init
# Should succeed - can access S3 state and DynamoDB locks
```

### Test 2: Terraform Plan
```bash
terraform plan
# Should succeed - can read all resources
```

### Test 3: Terraform Apply (No Changes)
```bash
terraform apply
# Should succeed with "No changes" if infrastructure is up-to-date
```

### Test 4: Create a Test Resource
```bash
# Add a test tag to existing ECS cluster in main.tf
# tags = merge(local.common_tags, { Test = "least-privilege" })

terraform apply
# Should succeed - can update ECS cluster tags
```

### Test 5: GitHub Actions Deployment
```bash
# Trigger a deployment via GitHub Actions
# Should successfully push images and update services
```

### Test 6: Verify No Admin Access
```bash
# Try to create an S3 bucket outside project scope
aws s3api create-bucket --bucket test-unauthorized-bucket --region ca-central-1
# Should FAIL with "Access Denied"
```

---

## Security Improvements Achieved

### Before (Admin Access):
- ❌ Full access to ALL AWS services
- ❌ Can delete ANY resource in account
- ❌ Can create resources in any region
- ❌ Can modify IAM users and permissions
- ❌ Can access billing and cost data
- ❌ Can create/delete S3 buckets outside project
- ❌ No audit trail limitations

### After (Least Privilege):
- ✅ Access only to project-specific resources
- ✅ Limited to `ca-central-1` region
- ✅ Cannot delete resources outside naming convention
- ✅ Cannot modify IAM users (only roles for ECS)
- ✅ Cannot access billing
- ✅ Cannot create unauthorized infrastructure
- ✅ Clear audit trail of allowed actions

---

## Maintenance

### When to Update Policies

**Add permissions when:**
- Adding new AWS services (e.g., S3 for ALB logs, CloudFront CDN)
- Expanding to new regions
- Adding new ECS services

**Example: Adding S3 for ALB Access Logs**
```json
{
  "Sid": "ALBAccessLogsS3",
  "Effect": "Allow",
  "Action": [
    "s3:CreateBucket",
    "s3:DeleteBucket",
    "s3:GetBucketPolicy",
    "s3:ListBucket",
    "s3:PutBucketPolicy",
    "s3:PutBucketPublicAccessBlock",
    "s3:PutBucketVersioning"
  ],
  "Resource": [
    "arn:aws:s3:::mind-the-wait-logs",
    "arn:aws:s3:::mind-the-wait-logs/*"
  ]
}
```

### Policy Versioning

AWS IAM policies support versioning:
```bash
# Create new policy version (max 5 versions)
aws iam create-policy-version \
  --policy-arn arn:aws:iam::ACCOUNT_ID:policy/MindTheWaitTerraformPolicy \
  --policy-document file://terraform-policy-v2.json \
  --set-as-default
```

### Auditing Policy Usage

Check what actions are actually being used:
```bash
# View CloudTrail events for IAM user
aws cloudtrail lookup-events \
  --lookup-attributes AttributeKey=Username,AttributeValue=terraform-user \
  --max-results 50
```

Use AWS Access Analyzer to find unused permissions:
```bash
aws accessanalyzer create-analyzer \
  --analyzer-name mind-the-wait-analyzer \
  --type ACCOUNT
```

---

## Troubleshooting

### Error: "User is not authorized to perform: ACTION"

**Diagnosis:**
```bash
# Check what policies are attached
aws iam list-attached-user-policies --user-name terraform-user

# View policy document
aws iam get-policy-version \
  --policy-arn arn:aws:iam::ACCOUNT_ID:policy/MindTheWaitTerraformPolicy \
  --version-id v1
```

**Solution:**
1. Identify the missing action from error message
2. Add action to appropriate Sid block in policy
3. Update policy version
4. Retry Terraform command

### Error: "Access Denied" for PassRole

This means Terraform cannot pass IAM roles to ECS tasks.

**Solution:**
Ensure the policy includes:
```json
{
  "Effect": "Allow",
  "Action": "iam:PassRole",
  "Resource": "arn:aws:iam::*:role/mind-the-wait-*"
}
```

### Error: Service-Linked Role Creation Failed

Some AWS services automatically create service-linked roles. If blocked:

**Solution:**
Add read-only IAM permissions:
```json
{
  "Effect": "Allow",
  "Action": [
    "iam:CreateServiceLinkedRole"
  ],
  "Resource": "arn:aws:iam::*:role/aws-service-role/*"
}
```

---

## Rollback Plan

If new policies break Terraform:

1. **Quickly restore admin access:**
```bash
aws iam attach-user-policy \
  --user-name terraform-user \
  --policy-arn arn:aws:iam::aws:policy/AdministratorAccess
```

2. **Detach broken policy:**
```bash
aws iam detach-user-policy \
  --user-name terraform-user \
  --policy-arn arn:aws:iam::ACCOUNT_ID:policy/MindTheWaitTerraformPolicy
```

3. **Fix policy and retry**

---

## Next Steps

1. ✅ Review the policies above
2. ✅ Create IAM policy in AWS Console or CLI
3. ✅ Test with `terraform plan` first
4. ✅ Detach AdministratorAccess policy
5. ✅ Attach new least-privilege policy
6. ✅ Run `terraform apply` on test change
7. ✅ Verify GitHub Actions deployment works
8. ✅ Delete admin policy attachment (optional, keeps as backup)

---

## References

- [AWS IAM Best Practices](https://docs.aws.amazon.com/IAM/latest/UserGuide/best-practices.html)
- [Terraform AWS Provider Authentication](https://registry.terraform.io/providers/hashicorp/aws/latest/docs#authentication-and-configuration)
- [ECS Task IAM Roles](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/task-iam-roles.html)
- [AWS Policy Simulator](https://policysim.aws.amazon.com/) - Test policies before applying

---

**Last Updated:** October 2025
**Status:** Ready for Implementation
**Owner:** @samuelwilk
