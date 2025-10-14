terraform {
  backend "s3" {
    bucket         = "mind-the-wait-terraform-state"
    key            = "prod/terraform.tfstate"
    region         = "ca-central-1"
    encrypt        = true
    dynamodb_table = "mind-the-wait-terraform-locks"
    profile        = "mind-the-wait"
  }

  required_version = ">= 1.6"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.5"
    }
  }
}

provider "aws" {
  region  = var.aws_region
  profile = "mind-the-wait"

  default_tags {
    tags = {
      Environment = "production"
      Project     = "mind-the-wait"
      ManagedBy   = "terraform"
    }
  }
}
