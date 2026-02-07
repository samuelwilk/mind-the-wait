output "zone_id" {
  description = "Cloudflare zone ID"
  value       = data.cloudflare_zone.main.id
}

output "root_record_id" {
  description = "Root A record ID"
  value       = cloudflare_record.root.id
}
