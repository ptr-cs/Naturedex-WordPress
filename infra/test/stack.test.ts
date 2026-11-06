import { App } from 'aws-cdk-lib';
import { Match, Template } from 'aws-cdk-lib/assertions';
import { beforeAll, describe, expect, it } from 'vitest';
import { NaturedexStack } from '../lib/naturedex-stack.js';

function synth(extra = {}) {
  return Template.fromStack(
    new NaturedexStack(new App(), 'Test', {
      env: { region: 'us-east-1' },
      adminEmail: 'admin@example.com',
      ...extra,
    }),
  );
}

describe('the Naturedex AWS stack', () => {
  let template: Template;
  beforeAll(() => {
    template = synth();
  });

  it('adds no NAT gateway, the largest avoidable fixed cost', () => {
    template.resourceCountIs('AWS::EC2::NatGateway', 0);
  });

  it('keeps the load balancer and database off the internet', () => {
    template.hasResourceProperties(
      'AWS::ElasticLoadBalancingV2::LoadBalancer',
      { Scheme: 'internal' },
    );
    template.hasResourceProperties('AWS::RDS::DBInstance', {
      PubliclyAccessible: false,
      StorageEncrypted: true,
      DeletionProtection: true,
    });
  });

  it('lets only the load balancer reach the tasks', () => {
    const rules = Object.values(
      template.findResources('AWS::EC2::SecurityGroupIngress'),
    ).map((resource) => resource.Properties);
    const inline = Object.values(
      template.findResources('AWS::EC2::SecurityGroup'),
    ).flatMap((resource) => resource.Properties.SecurityGroupIngress ?? []);
    const open = [...rules, ...inline].filter(
      (rule) => rule.CidrIp === '0.0.0.0/0' || rule.CidrIpv6 === '::/0',
    );
    expect(open).toEqual([]);
  });

  it('admits CloudFront to the load balancer through AWS\'s managed list', () => {
    template.hasResourceProperties('AWS::EC2::SecurityGroupIngress', {
      Description: 'CloudFront VPC origin',
      SourcePrefixListId: Match.stringLikeRegexp('^pl-'),
      FromPort: 80,
    });
  });

  it('protects stored data from stack deletion', () => {
    template.hasResource('AWS::RDS::DBInstance', {
      DeletionPolicy: 'Snapshot',
    });
    template.hasResource('AWS::EFS::FileSystem', { DeletionPolicy: 'Retain' });
  });

  it('requires TLS to the database rather than relying on the default', () => {
    template.hasResourceProperties('AWS::RDS::DBParameterGroup', {
      Parameters: { require_secure_transport: '1' },
    });
  });

  it('passes secrets by reference, never as plain environment values', () => {
    for (const definition of Object.values(
      template.findResources('AWS::ECS::TaskDefinition'),
    )) {
      const [container] = definition.Properties.ContainerDefinitions;
      const plain = container.Environment.map(
        (entry: { Name: string }) => entry.Name,
      );
      expect(
        plain.filter((name: string) => /PASSWORD|SECRET/.test(name)),
      ).toEqual([]);
    }
  });

  it('gives the admin password to the install task, not the web container', () => {
    const containers = Object.values(
      template.findResources('AWS::ECS::TaskDefinition'),
    ).map((definition) => definition.Properties.ContainerDefinitions[0]);
    const secrets = (container: { Secrets: Array<{ Name: string }> }) =>
      container.Secrets.map((entry) => entry.Name);
    const web = containers.find((container) => container.PortMappings?.length);
    const install = containers.find((container) =>
      container.Command?.includes('naturedex-bootstrap'),
    );
    expect(secrets(web)).toContain('NATUREDEX_DB_PASSWORD');
    expect(secrets(web)).not.toContain('NATUREDEX_ADMIN_PASSWORD');
    expect(secrets(install)).toContain('NATUREDEX_ADMIN_PASSWORD');
  });

  it('limits what a compromised task could reach', () => {
    const groups = Object.values(
      template.findResources('AWS::EC2::SecurityGroup', {
        Properties: { GroupDescription: Match.stringLikeRegexp('WordPress tasks') },
      }),
    );
    const open = groups[0].Properties.SecurityGroupEgress.filter(
      (rule: { CidrIp?: string }) => rule.CidrIp === '0.0.0.0/0',
    );
    expect(open).toEqual([expect.objectContaining({ FromPort: 443, ToPort: 443 })]);
  });

  it('drops malformed headers at the load balancer', () => {
    template.hasResourceProperties('AWS::ElasticLoadBalancingV2::LoadBalancer', {
      LoadBalancerAttributes: Match.arrayWith([
        { Key: 'routing.http.drop_invalid_header_fields.enabled', Value: 'true' },
      ]),
    });
  });

  it('separates signed-in visitors in the page cache', () => {
    template.hasResourceProperties('AWS::CloudFront::CachePolicy', {
      CachePolicyConfig: Match.objectLike({
        ParametersInCacheKeyAndForwardedToOrigin: Match.objectLike({
          CookiesConfig: {
            CookieBehavior: 'whitelist',
            Cookies: ['wordpress_logged_in_naturedex'],
          },
        }),
      }),
    });
  });

  it('never caches sign-in, wp-admin, the REST API, or the Writing desk', () => {
    const [resource] = Object.values(
      template.findResources('AWS::CloudFront::Distribution'),
    );
    const behaviors = resource.Properties.DistributionConfig.CacheBehaviors as Array<{
      PathPattern: string;
      CachePolicyId: string;
    }>;
    const cachingDisabled = '4135ea2d-6df8-44a3-9df3-4b5a84be39ad';
    for (const path of ['/wp-admin/*', '/wp-login.php', '/wp-json/*', '/editor'])
      expect(
        behaviors.find((behavior) => behavior.PathPattern === path)
          ?.CachePolicyId,
      ).toBe(cachingDisabled);
  });

  it('allows writes only on the uncached behaviors', () => {
    const [resource] = Object.values(
      template.findResources('AWS::CloudFront::Distribution'),
    );
    const config = resource.Properties.DistributionConfig;
    expect(config.DefaultCacheBehavior.AllowedMethods).not.toContain('POST');
  });

  it('blocks XML-RPC and the web installer, and limits login attempts', () => {
    template.hasResourceProperties('AWS::WAFv2::WebACL', {
      Scope: 'CLOUDFRONT',
      Rules: Match.arrayWith([
        Match.objectLike({ Name: 'BlockXmlRpc' }),
        Match.objectLike({ Name: 'BlockWebInstaller' }),
        Match.objectLike({ Name: 'LimitLoginAttempts' }),
      ]),
    });
  });

  it('ignores query strings that could force cache misses', () => {
    const policies = Object.values(
      template.findResources('AWS::CloudFront::CachePolicy'),
    ).map(
      (policy) =>
        policy.Properties.CachePolicyConfig
          .ParametersInCacheKeyAndForwardedToOrigin.QueryStringsConfig,
    );
    expect(policies).toHaveLength(2);
    for (const config of policies) {
      expect(config.QueryStringBehavior).toBe('whitelist');
      expect(config.QueryStrings).not.toContain('collection');
    }
  });

  it('rate limits requests that reach the origin, but not cached assets', () => {
    const [acl] = Object.values(template.findResources('AWS::WAFv2::WebACL'));
    const rule = acl.Properties.Rules.find(
      (entry: { Name: string }) => entry.Name === 'LimitOriginRequests',
    );
    expect(rule.Action).toEqual({ Block: {} });
    expect(rule.Statement.RateBasedStatement.Limit).toBe(300);
    expect(JSON.stringify(rule.Statement)).toContain('/wp-content/');
    const priorities = acl.Properties.Rules.map(
      (entry: { Priority: number }) => entry.Priority,
    );
    expect(new Set(priorities).size).toBe(priorities.length);
  });

  it('keeps an audit trail that can prove it was not altered', () => {
    template.hasResourceProperties('AWS::CloudTrail::Trail', {
      IsMultiRegionTrail: true,
      IncludeGlobalServiceEvents: true,
      EnableLogFileValidation: true,
    });
    template.hasResourceProperties('AWS::S3::Bucket', {
      PublicAccessBlockConfiguration: Match.objectLike({
        BlockPublicAcls: true,
        RestrictPublicBuckets: true,
      }),
    });
  });

  it('detects threats and sends severe ones to the alarm topic', () => {
    template.hasResourceProperties('AWS::GuardDuty::Detector', { Enable: true });
    template.hasResourceProperties('AWS::Events::Rule', {
      EventPattern: Match.objectLike({ source: ['aws.guardduty'] }),
    });
  });

  it('logs WAF traffic without storing credentials', () => {
    const [config] = Object.values(
      template.findResources('AWS::WAFv2::LoggingConfiguration'),
    );
    const redacted = config.Properties.RedactedFields.map(
      (field: { SingleHeader: { Name: string } }) => field.SingleHeader.Name,
    );
    expect(redacted).toEqual(['cookie', 'authorization', 'x-wp-nonce']);
    expect(JSON.stringify(config.Properties.LogDestinationConfigs)).toContain(
      'aws-waf-logs-',
    );
    expect(JSON.stringify(config.Properties.LogDestinationConfigs)).not.toContain(
      ':*',
    );
  });

  it('runs WordPress cron on a schedule, because cached visits cannot trigger it', () => {
    template.hasResourceProperties('AWS::Events::Rule', {
      ScheduleExpression: 'rate(15 minutes)',
    });
  });

  it('raises alarms, and subscribes an address only when given one', () => {
    template.resourceCountIs('AWS::CloudWatch::Alarm', 3);
    template.resourceCountIs('AWS::SNS::Subscription', 0);
    synth({ alarmEmail: 'alarms@example.com' }).resourceCountIs(
      'AWS::SNS::Subscription',
      1,
    );
  });

  it('leaves spending limits to the account budgets', () => {
    template.resourceCountIs('AWS::Budgets::Budget', 0);
  });

  it('adds a certificate and DNS record only when given a domain', () => {
    template.resourceCountIs('AWS::CertificateManager::Certificate', 0);
    const withDomain = synth({
      domainName: 'naturedex.example.com',
      hostedZoneId: 'Z123456ABCDEFG',
      hostedZoneName: 'example.com',
    });
    withDomain.resourceCountIs('AWS::CertificateManager::Certificate', 1);
    withDomain.hasResourceProperties('AWS::Route53::RecordSet', {
      Name: 'naturedex.example.com.',
    });
  });
});
