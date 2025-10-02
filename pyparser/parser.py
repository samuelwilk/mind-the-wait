import asyncio, os, time, json, signal
import aiohttp
import redis
from google.transit import gtfs_realtime_pb2

REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379")
VEH_URL   = os.getenv("VEH_URL")
TRIP_URL  = os.getenv("TRIP_URL")
ALERT_URL = os.getenv("ALERT_URL")
POLL_SEC  = int(os.getenv("POLL_SEC", "12"))

r = redis.Redis.from_url(REDIS_URL)

def trim(kind, feed):
    ts = feed.header.timestamp or int(time.time())
    out = []
    for e in feed.entity:
        if kind == "vehicles" and e.HasField("vehicle") and e.vehicle.HasField("position"):
            v, p = e.vehicle, e.vehicle.position
            out.append({
                "id": e.id,
                "trip": v.trip.trip_id if v.HasField("trip") else None,
                "route": v.trip.route_id if v.HasField("trip") else None,
                "lat": p.latitude, "lon": p.longitude,
                "ts": int(v.timestamp or ts)
            })
        elif kind == "trips" and e.HasField("trip_update"):
            tu = e.trip_update
            out.append({
                "trip": tu.trip.trip_id, "route": tu.trip.route_id,
                "rel": tu.trip.schedule_relationship,
                "stops": [{
                    "stop_id": stu.stop_id, "seq": stu.stop_sequence,
                    "arr": stu.arrival.time if stu.HasField("arrival") else None,
                    "dep": stu.departure.time if stu.HasField("departure") else None,
                    "delay": stu.arrival.delay if stu.HasField("arrival") and stu.arrival.HasField("delay") else None,
                    "rel": stu.schedule_relationship
                } for stu in tu.stop_time_update]
            })
        elif kind == "alerts" and e.HasField("alert"):
            out.append({"cause": e.alert.cause, "effect": e.alert.effect})
    return ts, out

async def fetch_pb(session, url):
    async with session.get(url, headers={"User-Agent": "MindTheWait/1.0"}, timeout=10) as resp:
        resp.raise_for_status()
        data = await resp.read()
        feed = gtfs_realtime_pb2.FeedMessage()
        feed.ParseFromString(data)
        return feed

async def poll_loop(kind, url, interval, initial_delay=0):
    await asyncio.sleep(initial_delay)
    async with aiohttp.ClientSession() as session:
        while True:
            try:
                feed = await fetch_pb(session, url)
                ts, out = trim(kind, feed)
                r.hset(f"mtw:{kind}", mapping={"ts": str(ts), "json": json.dumps(out)})
                r.expire(f"mtw:{kind}", 180)
                print(time.strftime("%Y-%m-%d %H:%M:%S"), f"{kind}: n={len(out)} ts={ts}")
            except Exception as e:
                print(time.strftime("%Y-%m-%d %H:%M:%S"), f"{kind} ERR:", e)
            await asyncio.sleep(interval)

async def main():
    # vehicles every POLL_SEC, trips slightly offset, alerts slower
    await asyncio.gather(
        poll_loop("vehicles", VEH_URL, POLL_SEC, 0),
        poll_loop("trips",    TRIP_URL, max(8, POLL_SEC), 4),
        poll_loop("alerts",   ALERT_URL, 15, 6),
    )

if __name__ == "__main__":
    # graceful shutdown
    loop = asyncio.get_event_loop()
    for s in (signal.SIGINT, signal.SIGTERM):
        loop.add_signal_handler(s, loop.stop)
    try:
        loop.run_until_complete(main())
    finally:
        loop.close()
