# AWS deployment plan

**Status: planned, not deployed.** No AWS account has been touched. The stack in `infra/` synthesizes and passes its tests offline, and the repository has no deploy script.

## Recommendation

Run WordPress as one container on ECS Fargate behind CloudFront, with RDS for MySQL and EFS for uploads. It costs **roughly $65–70 a month** at low traffic.

That is several times the price of a single small server, because the design pays for replaceable servers, managed backups, and an edge firewall. If cost matters more than those, see [the cheaper alternative](#cheaper-alternative).

## Architecture

```
Visitor ──HTTPS──▶ CloudFront + WAF
                      │  VPC origin (no public load balancer)
                      ▼
          Internal Application Load Balancer
                      │
                      ▼
          ECS Fargate task (Apache, PHP, WordPress)
            │                    │
            ▼                    ▼
   RDS MySQL 8.4         EFS (wp-content/uploads)

EventBridge ─ every 15 min ─▶ one-off task: wp cron event run --due-now
```

Everything is one stack in **us-east-1**, the only region where CloudFront accepts WAF ACLs and certificates.

| Decision | Choice | Why |
| --- | --- | --- |
| Compute | Fargate, 0.5 vCPU, 1 GB, ARM, one task | No servers to patch. Code ships in an immutable image, so a task can be replaced at any time |
| Database | RDS MySQL 8.4, `db.t4g.micro`, Single-AZ | WordPress's standard database. Multi-AZ would double the cost for a site that tolerates brief downtime |
| Uploads | EFS | The only files that change at runtime. Needs no offload plugin, and media volume here is tiny |
| Public entry | CloudFront only | The load balancer has no public address, so it can't be reached around the WAF |
| Network | No NAT gateway | Tasks sit in public subnets with no inbound internet rules. A NAT gateway would cost about $32 a month, more than the containers |
| Secrets | Secrets Manager, injected by ECS | `wp-config.php` holds no secrets. All WordPress keys derive from one secret |

## How caching works

Public pages hold nothing specific to a visitor, because the nonce appears only on the Writing desk. That lets CloudFront cache them.

| Request | Behavior |
| --- | --- |
| Public pages | Cached for 5 minutes (404s for 1 minute) |
| `/wp-content/*`, `/wp-includes/*` | Cached for a day; theme assets change address with each build |
| `/wp-admin/*`, `/wp-login.php`, `/wp-json/*`, `/editor` | Never cached |
| Signed-in visitors on public pages | Bypass the shared cache, so draft previews work |

The last row needs a known cookie name, so `wp-config.php` fixes `COOKIEHASH`. CloudFront adds `wordpress_logged_in_naturedex` to the cache key, and WordPress answers those requests with `no-store`.

**A published or edited note can take up to 5 minutes to appear for visitors.** Instant updates would need an invalidation step on save. That is not worth building for a journal.

## Security

- WAF: AWS common, known-bad-input, and WordPress rule sets, plus rules that block XML-RPC and the web installer and limit `/wp-login.php` to 20 requests per 5 minutes per IP.
- Two common-rule-set rules are set to count, not block: `SizeRestrictions_BODY` and `CrossSiteScripting_BODY`. Otherwise they would reject long field notes and notes that mention HTML. WordPress validates and escapes that content.
- The web installer is blocked at both WAF and Apache. Between stack creation and the bootstrap task the database is empty, and that page would let anyone claim the site.
- WordPress can't change its own code on the server (`DISALLOW_FILE_MODS`), and updates arrive only through a new image.
- PHP can't execute from uploads.
- The plugin closes comments, pingbacks, XML-RPC methods, application passwords, anonymous user listing, and author archives.
- Database connections require TLS, set explicitly in a parameter group. The server certificate is not verified, because that would need the RDS CA bundle in the image. The traffic stays inside the VPC.
- Security headers, including the CSP, are sent by CloudFront. The browser suite loads the site under that exact CSP. The policy blocks third-party embeds such as YouTube, so adding those means widening `infra/lib/csp.ts`.

## Monthly cost estimate

On-demand, us-east-1, low traffic. Unit rates were checked against the [AWS public price list files](https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/index.json) on November 6, 2026. The rates are official; the usage behind the small items is estimated, and tax is not included.

| Item | Per month |
| --- | ---: |
| Load balancer, including light usage | $17.50 |
| Fargate, one task around the clock | $14.40 |
| RDS `db.t4g.micro` and 20 GB gp3 | $14.00 |
| WAF: ACL, 6 rules, requests | $11.50 |
| Public IPv4 address for the task | $3.65 |
| Logs, ECR, alarms | $2.00 |
| Secrets Manager, 3 secrets | $1.20 |
| Scheduled cron tasks | $1.20 |
| EFS and backups | $1.00 |
| CloudFront at low traffic | $0–1 |
| **Total** | **about $67** |

A budget alert is set at $100 (`monthlyBudgetUsd`), and it fires on forecast spend.

### Cost of a short run

The stack costs about **$2.20 a day**, and charges stop when resources are deleted. The rates are hourly or prorated, except that a Route 53 hosted zone is a flat $0.50 a month.

| Running time | Cost |
| --- | ---: |
| 7 days | about $15 |
| 14 days | about $31 |
| 30 days | about $66 |

Allow a few dollars extra for failed first attempts. A domain is optional and extra.

After `cdk destroy`, the retained RDS snapshot ($0.095 per GB-month), EFS data, backups, and logs keep billing until deleted by hand.

**CloudFront flat-rate plans.** Pro, at $15 a month, includes WAF, with WordPress protections and 10M requests. That could replace the WAF line, but AWS lists private origin endpoints as a Business-tier feature ($200), which may rule out this VPC origin design. The stack uses pay-as-you-go. It is worth checking before deployment if WAF cost matters.

### Cheaper alternative

A single Lightsail WordPress instance would likely cost roughly a fifth as much (from memory, so check current prices). The trade-offs: you patch the server yourself, the database and uploads live on that one machine, there's no WAF, and an instance failure means restoring from a snapshot. This suits a personal site, and the plugin and theme install there unchanged. Only `docker/` and `infra/` are specific to Fargate.

## Decisions needed before deploying

1. **Which domain?** Without one, the site uses a `cloudfront.net` address. If you use a domain, it needs a Route 53 hosted zone.
2. **Which addresses** receive alarms and own the admin account?
3. **Which AWS account,** and does it have a dedicated deployment role?
4. **Where is the image built?** Deployment needs Docker, which this machine doesn't have. Options are installing it locally or adding CI such as GitHub Actions or CodeBuild.

## Deployment runbook

Not yet run. Treat the first pass as a test, and expect to fix things.

```sh
# 1. Tools: AWS CLI, Docker, credentials for the target account.
cd infra && npm ci

# 2. One time per account and region.
npx cdk bootstrap aws://ACCOUNT_ID/us-east-1

# 3. Review what will be created.
npx cdk diff \
  -c adminEmail=you@example.com -c alarmEmail=you@example.com \
  -c domainName=naturedex.example.com \
  -c hostedZoneId=Z0000000000 -c hostedZoneName=example.com

# 4. Create it, with the same context values. Run this yourself, deliberately.
npx cdk deploy -c ...

# 5. Install and seed WordPress, using the stack's outputs.
aws ecs run-task --cluster CLUSTER --task-definition TASK_ARN \
  --launch-type FARGATE \
  --network-configuration 'awsvpcConfiguration={subnets=[SUBNET_IDS],securityGroups=[TASK_SG],assignPublicIp=ENABLED}' \
  --overrides '{"containerOverrides":[{"name":"wordpress","command":["naturedex-bootstrap"]}]}'

# 6. Read the admin password, sign in at /editor, and change it.
aws secretsmanager get-secret-value --secret-id ADMIN_PASSWORD_SECRET_ARN
```

The admin username is `naturedex-admin`. Set your display name in wp-admin, because the author name appears on field notes. Confirm the SNS email subscription so alarms arrive.

### First-deployment checks

- [ ] `/wp-admin/install.php` and `/xmlrpc.php` return 403 **before** running the bootstrap task
- [ ] The site loads over HTTPS, with no redirect loop
- [ ] Sign in, then create, publish, and delete a field note from the Writing desk
- [ ] Upload an image in wp-admin, then replace the task and confirm the image survives
- [ ] A second `curl -I` of `/` shows `x-cache: Hit from cloudfront`
- [ ] A signed-in response is `no-store`, and draft preview works
- [ ] A long field note saves without a WAF block
- [ ] A scheduled post publishes within 15 minutes
- [ ] Twenty-plus bad logins get blocked
- [ ] Run the browser suite's scenarios against the site, including axe scans

## What has not been tested

Everything in the application was tested on Playground. None of the following has been run:

| Not tested | Risk | How it will be confirmed |
| --- | --- | --- |
| Docker image build | Medium. Docker isn't available on this machine, so the Dockerfile has never run | `docker build`, then the checks above |
| MySQL | Low. All queries go through WordPress APIs, but tests used SQLite | First deployment, or a local MySQL container |
| `wp-config.php` | Medium. Parsed on PHP 8.4 but never executed | First boot |
| Load balancer security group | Medium. It allows the VPC's address range, while AWS documents the CloudFront prefix list or service security group. If CloudFront gets 504s, change this first. After deployment it can be tightened to `CloudFront-VPCOrigins-Service-SG` | First request |
| WAF rule behavior | Medium. Managed rules can block legitimate wp-admin requests, especially in the block editor | Save posts in the block editor, and check sampled requests |
| Stack deployment | Medium. CDK and its tests check the template, not that AWS accepts it | `cdk deploy` |

## Operations

**Release.** `cdk deploy` builds the image and rolls it out. A new task must pass health checks before the old one stops, and a failing release rolls back automatically.

**Updating WordPress or PHP.** Change the versions in both `docker/Dockerfile` and `scripts/playground.mjs`, run `npm run verify`, then deploy. Nothing updates itself, so watch for WordPress security releases.

**Backups.** RDS keeps 7 days of point-in-time recovery. AWS Backup keeps daily EFS copies for 35 days. Code is in the repository. Do one restore drill early, because an untested backup isn't a backup.

**Availability.** Single task and Single-AZ database. Expect short interruptions during an AZ failure or database maintenance, though CloudFront keeps serving recently cached pages only until they expire. For higher availability, raise `desiredCount` to 2 and enable `multiAz`; EFS already supports this.

**Teardown.** `cdk destroy` keeps the data on purpose: the RDS instance has deletion protection and leaves a snapshot, and EFS is retained. Those keep costing money until removed by hand. CloudFront VPC origins can also take a while to delete.

## Sources

- [CloudFront VPC origins](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-vpc-origins.html)
- [CloudFront flat-rate plans](https://aws.amazon.com/cloudfront/pricing/)
- [Requiring TLS on RDS for MySQL](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/mysql-ssl-connections.require-ssl.html)
- [AWS public price list](https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/index.json)
