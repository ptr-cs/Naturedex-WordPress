import { App } from 'aws-cdk-lib';
import { NaturedexStack } from '../lib/naturedex-stack.js';

const app = new App();
const context = (key: string) => String(app.node.tryGetContext(key) ?? '');

// WordPress needs an address for its first account. This placeholder can't
// receive mail, so change it in wp-admin, or pass -c adminEmail=... to deploy.
new NaturedexStack(app, 'NaturedexWordPress', {
  env: { region: 'us-east-1' },
  adminEmail: context('adminEmail') || 'admin@example.com',
  alarmEmail: context('alarmEmail') || undefined,
  domainName: context('domainName') || undefined,
  hostedZoneId: context('hostedZoneId') || undefined,
  hostedZoneName: context('hostedZoneName') || undefined,
});
