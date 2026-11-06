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
    const [definition] = Object.values(
      template.findResources('AWS::ECS::TaskDefinition'),
    );
    const container = definition.Properties.ContainerDefinitions[0];
    const plain = container.Environment.map(
      (entry: { Name: string }) => entry.Name,
    );
    const secret = container.Secrets.map(
      (entry: { Name: string }) => entry.Name,
    );
    expect(secret).toEqual(
      expect.arrayContaining([
        'NATUREDEX_DB_PASSWORD',
        'NATUREDEX_AUTH_SECRET',
        'NATUREDEX_ADMIN_PASSWORD',
      ]),
    );
    expect(plain.filter((name: string) => /PASSWORD|SECRET/.test(name))).toEqual(
      [],
    );
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
