import os, redis
r = redis.Redis.from_url(os.getenv("REDIS_URL","redis://redis:6379"))
# if we have a recent vehicles ts, we're healthy
ts = r.hget("mtw:vehicles","ts")
exit(0 if ts else 1)
