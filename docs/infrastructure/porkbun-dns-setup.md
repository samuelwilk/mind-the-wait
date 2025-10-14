# Porkbun DNS Configuration for AWS Route 53

This guide walks you through updating your Porkbun domain nameservers to point to AWS Route 53 after deploying your infrastructure with Terraform.

## Overview

When you deploy the infrastructure with Terraform, it creates:

1. **Route 53 Hosted Zone**: AWS DNS service for your domain
2. **ACM SSL Certificate**: Automatically validated via DNS
3. **Route 53 Records**: DNS records pointing to your Application Load Balancer

To make your domain work with AWS, you need to update Porkbun's nameservers to use Route 53's nameservers.

## Prerequisites

- ✅ Terraform infrastructure deployed (`terraform apply` completed)
- ✅ Access to your Porkbun account
- ✅ Domain: `mind-the-wait.ca`

## Step 1: Get Route 53 Nameservers

After running `terraform apply`, get the Route 53 nameservers:

```bash
cd /Users/sam/Repos/mind-the-wait/terraform/environments/prod
terraform output route53_name_servers
```

Expected output (your values will be different):
```
[
  "ns-1234.awsdns-12.org",
  "ns-5678.awsdns-34.com",
  "ns-9012.awsdns-56.net",
  "ns-3456.awsdns-78.co.uk",
]
```

**Copy all 4 nameservers** - you'll need them in the next step.

## Step 2: Log Into Porkbun

1. Go to [Porkbun.com](https://porkbun.com)
2. Click **Account** → **Domain Management**
3. Find `mind-the-wait.ca` in your domain list
4. Click **Details** or **Manage**

## Step 3: Update Nameservers

### Option A: Using Porkbun Web Interface

1. **Navigate to DNS settings**:
   - On the domain details page, look for **Authoritative Nameservers** section
   - Or click **DNS** tab → **Authoritative Nameservers**

2. **Switch from Porkbun to Custom nameservers**:
   - Find the nameserver mode dropdown
   - Select **"Use custom nameservers"** or **"Use external nameservers"**

3. **Enter Route 53 nameservers**:
   - Remove any existing nameservers
   - Add all 4 Route 53 nameservers (from Step 1)
   - Example format:
     ```
     ns-1234.awsdns-12.org
     ns-5678.awsdns-34.com
     ns-9012.awsdns-56.net
     ns-3456.awsdns-78.co.uk
     ```

4. **Save changes**:
   - Click **Update** or **Save**
   - You should see a confirmation message

### Option B: Using Porkbun API (Advanced)

If you prefer automation:

```bash
# Get your API credentials from Porkbun Account → API Access
PORKBUN_API_KEY="your_api_key"
PORKBUN_SECRET="your_secret"

# Get Route 53 nameservers
NS1=$(terraform output -json route53_name_servers | jq -r '.[0]')
NS2=$(terraform output -json route53_name_servers | jq -r '.[1]')
NS3=$(terraform output -json route53_name_servers | jq -r '.[2]')
NS4=$(terraform output -json route53_name_servers | jq -r '.[3]')

# Update nameservers via API
curl -X POST https://porkbun.com/api/json/v3/domain/updateNs/mind-the-wait.ca \
  -H "Content-Type: application/json" \
  -d "{
    \"apikey\": \"$PORKBUN_API_KEY\",
    \"secretapikey\": \"$PORKBUN_SECRET\",
    \"ns\": [\"$NS1\", \"$NS2\", \"$NS3\", \"$NS4\"]
  }"
```

## Step 4: Verify DNS Propagation

DNS changes can take anywhere from **10 minutes to 48 hours** to propagate globally, but typically complete within 30 minutes.

### Check Nameservers

```bash
# Check what nameservers are currently set
dig NS mind-the-wait.ca +short

# Expected output (after propagation):
ns-1234.awsdns-12.org.
ns-5678.awsdns-34.com.
ns-9012.awsdns-56.net.
ns-3456.awsdns-78.co.uk.
```

### Check DNS Resolution

```bash
# Check if your domain resolves to the ALB
dig mind-the-wait.ca +short

# Expected output: ALB DNS name or IP addresses
```

### Online DNS Propagation Checkers

Check propagation status worldwide:
- [https://www.whatsmydns.net/#NS/mind-the-wait.ca](https://www.whatsmydns.net/#NS/mind-the-wait.ca)
- [https://dnschecker.org/#NS/mind-the-wait.ca](https://dnschecker.org/#NS/mind-the-wait.ca)

## Step 5: Verify SSL Certificate

Once DNS propagates, AWS will automatically validate your SSL certificate via DNS:

```bash
# Check certificate status
aws acm list-certificates --region ca-central-1 --profile mind-the-wait

# Get certificate details (replace ARN with your certificate ARN)
aws acm describe-certificate \
  --certificate-arn arn:aws:acm:ca-central-1:ACCOUNT_ID:certificate/CERT_ID \
  --region ca-central-1 \
  --profile mind-the-wait
```

Look for `"Status": "ISSUED"` in the output.

## Step 6: Test Your Application

Once DNS propagates and SSL validates (usually 15-30 minutes):

```bash
# Test HTTPS endpoint
curl https://mind-the-wait.ca/api/realtime

# Test redirect from HTTP to HTTPS
curl -I http://mind-the-wait.ca

# Expected: HTTP/1.1 301 Moved Permanently
# Location: https://mind-the-wait.ca/
```

Visit in browser:
- https://mind-the-wait.ca (should show your application)
- https://mind-the-wait.ca/api/realtime (should return JSON)

## Troubleshooting

### "Nameservers not updating"

**Problem**: After 1-2 hours, `dig NS mind-the-wait.ca` still shows old nameservers.

**Solutions**:
1. Verify you saved changes in Porkbun
2. Log out and log back into Porkbun to confirm nameservers are set
3. Contact Porkbun support if changes aren't persisting

### "SSL certificate stuck in Pending Validation"

**Problem**: Certificate status remains `PENDING_VALIDATION` after DNS propagates.

**Solutions**:
1. Verify nameservers are fully propagated globally (check whatsmydns.net)
2. Check Route 53 has the correct validation CNAME records:
   ```bash
   aws route53 list-resource-record-sets \
     --hosted-zone-id $(terraform output -raw hosted_zone_id) \
     --profile mind-the-wait
   ```
3. Wait 10-30 minutes - validation is automatic but can be slow

### "DNS resolves but site doesn't load"

**Problem**: `dig mind-the-wait.ca` works but browser shows error.

**Solutions**:
1. Check ALB is healthy:
   ```bash
   aws elbv2 describe-target-health \
     --target-group-arn $(terraform output -raw target_group_arn) \
     --region ca-central-1 \
     --profile mind-the-wait
   ```
2. Verify ECS tasks are running:
   ```bash
   aws ecs list-tasks \
     --cluster mind-the-wait-prod \
     --region ca-central-1 \
     --profile mind-the-wait
   ```
3. Check CloudWatch logs for errors

### "Browser shows SSL error"

**Problem**: Browser shows "Your connection is not private" or SSL warnings.

**Solutions**:
1. Wait for certificate validation (can take 15-30 minutes after DNS propagates)
2. Clear browser cache and try in incognito mode
3. Check certificate status: `aws acm describe-certificate ...`

## Important Notes

⚠️ **DO NOT delete the Route 53 hosted zone**: If you delete it, you'll get different nameservers and need to update Porkbun again.

⚠️ **Keep Porkbun registration active**: Porkbun is still your domain registrar. You're only changing DNS hosting to Route 53.

⚠️ **Email routing**: If you use Porkbun's email forwarding, you'll need to recreate MX records in Route 53.

## Timeline Summary

| Step | Time |
|------|------|
| Update nameservers in Porkbun | Immediate |
| DNS propagation globally | 10-30 minutes (up to 48 hours) |
| SSL certificate validation | 5-15 minutes after DNS propagates |
| **Total expected time** | **20-60 minutes** |

## DNS Records Reference

After setup, Route 53 will manage these records (automatically created by Terraform):

```
mind-the-wait.ca          A     ALIAS -> ALB
www.mind-the-wait.ca      A     ALIAS -> ALB
_acm-validation.mind...   CNAME -> SSL validation record
```

You can add additional records via Terraform by editing `terraform/modules/dns/main.tf`.

## Cost

Route 53 costs for your setup:
- **Hosted zone**: $0.50/month
- **DNS queries**: First 1 billion queries = $0.40/million
  - For 100K queries/month: ~$0.04/month
- **Total**: ~$0.54/month

## Next Steps

After DNS is configured and propagated:

1. ✅ Verify site loads at https://mind-the-wait.ca
2. ✅ Test API endpoints
3. ✅ Run database migrations (see deployment checklist)
4. ✅ Load GTFS data
5. ✅ Monitor CloudWatch logs for any issues

## Reference

- [Porkbun DNS Documentation](https://kb.porkbun.com/category/2-dns)
- [AWS Route 53 Documentation](https://docs.aws.amazon.com/route53/)
- [ACM Certificate Validation](https://docs.aws.amazon.com/acm/latest/userguide/dns-validation.html)
