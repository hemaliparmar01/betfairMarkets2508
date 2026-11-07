# Daily REST API usage report

The current schedule accounts for **3,264 Betfair calls** and **3,072 SportsAPI Pro calls** over a full 24-hour day, assuming every scheduled sync runs once and makes one REST request. These figures cover the recurring fixture and odds feed syncs. Actual usage varies with worker uptime, quota checks, and extra requests for individual matches.

## Betfair

| API call | Frequency | Calls per run | Calls per day |
|---|---:|---:|---:|
| Live feeds for football, cricket, ice hockey, American football, baseball, basketball, and tennis | Every 5 minutes | 7 | 2,016 |
| Horse Racing and Greyhounds feeds | Every 5 minutes | 2 | 576 |
| Prematch feeds for the seven sports in the first row | Every 15 minutes | 7 | 672 |
| **Scheduled EOD total** | | | **3,264** |

The five-minute runs start at minutes 00, 05, 10, and so on. The seven prematch calls are added at minutes 00, 15, 30, and 45. That gives **9 calls** at a regular five-minute slot and **16 calls** at a quarter-hour slot.

## SportsAPI Pro

| API call | Frequency | Calls per run | Calls per day |
|---|---:|---:|---:|
| Live feeds for the same seven sports plus Horse Racing | Every 5 minutes | 8 | 2,304 |
| Prematch schedules for those eight sports | Every 15 minutes | 8 | 768 |
| **Scheduled EOD total** | | | **3,072** |

That gives **8 calls** at a regular five-minute slot and **16 calls** at a quarter-hour slot. The football prematch statistics and V6 statistics jobs also check for due matches every minute. Prematch statistics can use up to six requests per due match. Images, Bet365 odds, timeline backfills, and recovery calls add variable usage.

The scheduler figures come from [routes/console.php](routes/console.php). WebSocket traffic is separate from these REST call counts.

## End-of-day totals

| Provider | Scheduled REST calls per day | Actual usage evidence |
|---|---:|---|
| **Betfair** | **3,264** | Exact daily total unavailable from local logs, which do not record every successful call. |
| **SportsAPI Pro** | **3,072** | On 7 November 2026, the provider reported that **75,000 requests** had used the full daily quota. The logs do not establish the exact number of attempts made after the quota was reached. |
| **Combined** | **6,336** | Exact combined daily usage is unavailable. |
