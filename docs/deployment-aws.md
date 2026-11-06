# AWS deployment plan

**Status: deployed** to us-east-1 on November 6, 2026, through the manual Deploy workflow in GitHub Actions. The stack is meant to run around an interview and then be torn down.

## Recommendation

Run WordPress as one container on ECS Fargate behind CloudFront, with RDS for MySQL and EFS for uploads. It costs **roughly $70 a month** at low traffic.

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
| Public pages | Cached for 5 minutes (404s for 1 minute). Query strings WordPress doesn't need are ignored, so they can't force a cache miss |
| `/wp-content/*`, `/wp-includes/*` | Cached for a day; theme assets change address with each build |
| `/wp-admin/*`, `/wp-login.php`, `/wp-json/*`, `/editor` | Never cached |
| Signed-in visitors on public pages | Bypass the shared cache, so draft previews work |

The last row needs a known cookie name, so `wp-config.php` fixes `COOKIEHASH`. CloudFront adds `wordpress_logged_in_naturedex` to the cache key, and WordPress answers those requests with `no-store`.

**A published or edited note can take up to 5 minutes to appear for visitors.** Instant updates would need an invalidation step on save. That is not worth building for a journal.

## Security

- WAF: AWS common, known-bad-input, and WordPress rule sets, plus rules that block XML-RPC and the web installer, limit `/wp-login.php` to 20 requests per 5 minutes per IP, and limit non-asset requests to 300 per 5 minutes per IP.
- Audit trail: CloudTrail with file validation (kept 90 days in S3), GuardDuty, and WAF logs of every request (kept 30 days). Severe GuardDuty findings go to the alarm topic. WAF logs redact `cookie`, `authorization`, and `x-wp-nonce`.
- Two common-rule-set rules are set to count, not block: `SizeRestrictions_BODY` and `CrossSiteScripting_BODY`. Otherwise they would reject long field notes and notes that mention HTML. WordPress validates and escapes that content.
- The web installer is blocked at both WAF and Apache, along with other WordPress endpoints this site doesn't use. Between stack creation and the bootstrap task the database is empty, and that page would let anyone claim the site.
- WordPress can't change its own code on the server (`DISALLOW_FILE_MODS`), and updates arrive only through a new image.
- PHP can't execute from uploads.
- The plugin closes comments, pingbacks, XML-RPC methods, application passwords, anonymous user listing, cross-origin API access, and author archives. Real and made-up author addresses return the same 404.
- The admin password exists only in the one-off install task, not in the container that serves visitors.
- Tasks can only reach HTTPS, the database, and EFS outbound.
- The image applies OS security updates at build time. ECR scans each pushed image; review results after a deploy.
- Database connections require TLS, set explicitly in a parameter group. The server certificate is not verified, because that would need the RDS CA bundle in the image. The traffic stays inside the VPC.
- Security headers, including the CSP, are sent by CloudFront. The browser suite loads the site under that exact CSP. The policy blocks third-party embeds such as YouTube, so adding those means widening `infra/lib/csp.ts`.

## Monthly cost estimate

On-demand, us-east-1, low traffic. Unit rates were checked against the [AWS public price list files](https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/index.json) on November 6, 2026. The rates are official; the usage behind the small items is estimated, and tax is not included.

| Item | Per month |
| --- | ---: |
| Load balancer, including light usage | $17.50 |
| Fargate, one task around the clock | $14.40 |
| RDS `db.t4g.micro` and 20 GB gp3 | $14.00 |
| WAF: ACL, 7 rules, requests | $12.50 |
| CloudTrail, WAF logs | $1.00 |
| GuardDuty (free for 30 days) | $1–4 |
| Public IPv4 address for the task | $3.65 |
| Logs, ECR, alarms | $2.00 |
| Secrets Manager, 3 secrets | $1.20 |
| Scheduled cron tasks | $1.20 |
| EFS and backups | $1.00 |
| CloudFront at low traffic | $0–1 |
| **Total** | **about $70–73** |

Spending limits are set as AWS budgets on the account, not in this stack.

### Cost of a short run

The stack costs about **$2.30 a day**, and charges stop when resources are deleted. The rates are hourly or prorated, except that a Route 53 hosted zone is a flat $0.50 a month.

| Running time | Cost |
| --- | ---: |
| 7 days | about $16 |
| 14 days | about $32 |
| 30 days | about $70 |

Allow a few dollars extra for failed first attempts. A domain is optional and extra.

After `cdk destroy`, the retained RDS snapshot ($0.095 per GB-month), EFS data, backups, and logs keep billing until deleted by hand.

**CloudFront flat-rate plans.** Pro, at $15 a month, includes WAF, with WordPress protections and 10M requests. That could replace the WAF line, but AWS lists private origin endpoints as a Business-tier feature ($200), which may rule out this VPC origin design. The stack uses pay-as-you-go. It is worth checking before deployment if WAF cost matters.

### Cheaper alternative

A single Lightsail WordPress instance would likely cost roughly a fifth as much (from memory, so check current prices). The trade-offs: you patch the server yourself, the database and uploads live on that one machine, there's no WAF, and an instance failure means restoring from a snapshot. This suits a personal site, and the plugin and theme install there unchanged. Only `docker/` and `infra/` are specific to Fargate.

## Deployment

GitHub Actions does the work, so Docker isn't needed locally. CI runs every test, then boots the production image against MySQL with TLS required (`docker/smoke-test.sh`). The Deploy workflow is started by hand and signs in to AWS through OIDC, with no stored keys.

### One-time setup

```sh
cd infra && npm ci
npx cdk bootstrap aws://ACCOUNT_ID/us-east-1

# On a new account, create this first, or the first ECS deploy can fail.
aws iam create-service-linked-role --aws-service-name ecs.amazonaws.com

# Scan every image CDK pushes. Bootstrapping again can reset this.
aws ecr put-image-scanning-configuration --repository-name ASSETS_REPOSITORY \
  --image-scanning-configuration scanOnPush=true

# The deploy role. See the template for how to find the subject.
aws cloudformation deploy --template-file infra/github-oidc.yaml \
  --stack-name NaturedexGitHubOidc --capabilities CAPABILITY_NAMED_IAM \
  --parameter-overrides Repository=SUBJECT

gh variable set AWS_DEPLOY_ROLE_ARN --body ROLE_ARN
```

The role trusts only the `main` branch of this repository, and can only assume the CDK bootstrap roles.

### Each deployment

```sh
gh workflow run deploy.yml
```

The first deployment takes about 18 minutes. Small changes take a few.

### First install

Run this once, after the first deployment, with the stack's outputs. Confirm first that `/wp-admin/install.php` returns 403.

```sh
aws ecs run-task --cluster CLUSTER --task-definition BOOTSTRAP_TASK_ARN \
  --launch-type FARGATE \
  --network-configuration 'awsvpcConfiguration={subnets=[SUBNET_IDS],securityGroups=[TASK_SG],assignPublicIp=ENABLED}'
```

The admin username is `naturedex-admin`. Read the password with `aws secretsmanager get-secret-value --secret-id ADMIN_PASSWORD_SECRET_ARN`. The placeholder admin email can't receive mail, so change it in wp-admin.

A new page such as `/status` also needs this task once after deploying, because seed content is imported rather than deployed. It's safe to repeat: it only adds what's missing.

### What the first deployment found

| Problem | Cause | Fix |
| --- | --- | --- |
| Deploy role was rejected | New repositories use GitHub's immutable OIDC subject, which includes IDs | Trust the exact subject from the GitHub API |
| Pages timed out through CloudFront | VPC origin traffic arrives from CloudFront addresses, not the VPC range | Allow the managed prefix list `com.amazonaws.global.cloudfront.origin-facing` |
| Author name leaked the username | WordPress defaults the display name to the login | Set it during bootstrap |
| Dialog source links were 11px tall | Original CSS. Mac fonts hid it; CI's Linux fonts exposed it | 24px minimum target |

## What has not been tested

- The rate limit on `/wp-login.php`, because triggering it would block the tester's address.
- The block editor screen itself. Saving block content through the REST API passes the WAF, but the WAF's managed rules could still react to other wp-admin requests.
- A restore from backup.
- Whether the two WAF rate limits trigger. Doing so would block the tester's address.

**Open image findings.** The November 6 scan reports 1 critical, 12 high, 34 medium, and 8 low findings in Debian 13 packages. The build applies every available update, and the five CVEs checked against Debian's tracker have no stable fix yet. The critical one is in the `tiffcrop` tool, which isn't in the image (only the `libtiff6` library is). Redeploy shortly before an important date to pick up fixes, then review the scan.

Verified on the live site: 33 browser checks, an uploaded image surviving a task replacement, and a scheduled post published by the EventBridge cron task.
- Manual accessibility checks, recorded in [accessibility.md](accessibility.md).

## Operations

**Release.** The Deploy workflow builds the image and rolls it out. A new task must pass health checks before the old one stops, and a failing release rolls back automatically.

**Updating WordPress or PHP.** Change the versions in both `docker/Dockerfile` and `scripts/playground.mjs`, run `npm run verify`, then deploy. Afterwards run `wp core update-db` in a one-off task, because `upgrade.php` is blocked. Nothing updates itself, so watch for WordPress security releases. Redeploying also rebuilds the image with current OS updates.

**Backups.** RDS keeps 7 days of point-in-time recovery. AWS Backup keeps daily EFS copies for 35 days. Code is in the repository. Do one restore drill early, because an untested backup isn't a backup.

**Availability.** Single task and Single-AZ database. Expect short interruptions during an AZ failure or database maintenance, though CloudFront keeps serving recently cached pages only until they expire. For higher availability, raise `desiredCount` to 2 and enable `multiAz`; EFS already supports this.

**Teardown.** The database has deletion protection, so turn that off first, then destroy the stack from `infra/`:

```sh
aws rds modify-db-instance --db-instance-identifier DB_ID --no-deletion-protection --apply-immediately
npx cdk destroy
```

This keeps data on purpose: RDS leaves a final snapshot, and EFS, its backups, and some logs remain. The CloudTrail bucket is also kept, and empties itself after 90 days. They keep billing until deleted by hand. GuardDuty and CloudTrail stop with the stack. Delete `NaturedexGitHubOidc` and `CDKToolkit` last if you're finished with the account.

## Sources

- [CloudFront VPC origins](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-vpc-origins.html)
- [CloudFront flat-rate plans](https://aws.amazon.com/cloudfront/pricing/)
- [Requiring TLS on RDS for MySQL](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/mysql-ssl-connections.require-ssl.html)
- [AWS public price list](https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/index.json)
