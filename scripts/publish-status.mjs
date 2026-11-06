// Publishes the sanitized test summary to the "status" branch, which the site's
// /status page reads. Runs in CI after the tests.
const { GITHUB_TOKEN, GITHUB_REPOSITORY, GITHUB_SHA, SUMMARY, IMAGE_RESULT } = process.env;
const url = `https://api.github.com/repos/${GITHUB_REPOSITORY}/contents/status.json`;
const headers = {
  Authorization: `Bearer ${GITHUB_TOKEN}`,
  Accept: 'application/vnd.github+json',
  'User-Agent': 'naturedex-ci',
};

const current = await fetch(`${url}?ref=status`, { headers });
if (!current.ok) throw new Error(`Could not read status: ${current.status}`);
const { sha, content } = await current.json();
const previous = JSON.parse(Buffer.from(content, 'base64').toString('utf8'));

const suites = JSON.parse(SUMMARY || '{}');
const at = new Date().toISOString();
const counts = Object.values(suites);
const ok =
  IMAGE_RESULT === 'success' &&
  counts.length === 4 &&
  counts.every((suite) => suite.failed === 0 && suite.passed > 0);

const status = {
  at,
  commit: GITHUB_SHA.slice(0, 7),
  suites,
  image: IMAGE_RESULT === 'success' ? 'passed' : 'failed',
  history: [{ at, ok }, ...(previous.history ?? [])].slice(0, 20),
};

const saved = await fetch(url, {
  method: 'PUT',
  headers,
  body: JSON.stringify({
    branch: 'status',
    message: 'Update test status',
    sha,
    content: Buffer.from(`${JSON.stringify(status, null, 2)}\n`).toString('base64'),
  }),
});
if (!saved.ok) throw new Error(`Could not save status: ${saved.status}`);
console.log(`Published status for ${status.commit}: ${ok ? 'passing' : 'failing'}`);
