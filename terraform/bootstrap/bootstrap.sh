#!/bin/bash
# Bootstrap Terraform backend resources for mind-the-wait

set -e

AWS_REGION="ca-central-1"
S3_BUCKET="mind-the-wait-terraform-state"
DYNAMODB_TABLE="mind-the-wait-terraform-locks"
AWS_PROFILE="mind-the-wait"

echo "🚀 Bootstrapping Terraform backend..."
echo "   Region: ${AWS_REGION}"
echo "   S3 Bucket: ${S3_BUCKET}"
echo "   DynamoDB Table: ${DYNAMODB_TABLE}"
echo "   AWS Profile: ${AWS_PROFILE}"
echo ""

# Create S3 bucket
echo "📦 Creating S3 bucket..."
aws s3 mb "s3://${S3_BUCKET}" --region "${AWS_REGION}" --profile "${AWS_PROFILE}" || echo "   (Bucket already exists)"

# Enable versioning
echo "🔄 Enabling versioning..."
aws s3api put-bucket-versioning \
  --bucket "${S3_BUCKET}" \
  --versioning-configuration Status=Enabled \
  --profile "${AWS_PROFILE}"

# Enable encryption
echo "🔒 Enabling encryption..."
aws s3api put-bucket-encryption \
  --bucket "${S3_BUCKET}" \
  --server-side-encryption-configuration '{
    "Rules": [{
      "ApplyServerSideEncryptionByDefault": {
        "SSEAlgorithm": "AES256"
      }
    }]
  }' \
  --profile "${AWS_PROFILE}"

# Block public access
echo "🚫 Blocking public access..."
aws s3api put-public-access-block \
  --bucket "${S3_BUCKET}" \
  --public-access-block-configuration \
    BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true \
  --profile "${AWS_PROFILE}"

# Create DynamoDB table
echo "🗄️  Creating DynamoDB table..."
aws dynamodb create-table \
  --table-name "${DYNAMODB_TABLE}" \
  --attribute-definitions AttributeName=LockID,AttributeType=S \
  --key-schema AttributeName=LockID,KeyType=HASH \
  --billing-mode PAY_PER_REQUEST \
  --region "${AWS_REGION}" \
  --profile "${AWS_PROFILE}" || echo "   (Table already exists)"

echo ""
echo "✅ Bootstrap complete!"
echo ""
echo "Backend resources created:"
echo "  📦 S3 Bucket: ${S3_BUCKET}"
echo "  🔒 DynamoDB Table: ${DYNAMODB_TABLE}"
echo ""
echo "Next steps:"
echo "  cd ../environments/prod"
echo "  terraform init"
