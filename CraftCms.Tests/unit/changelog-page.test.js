const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const rootDir = path.resolve(__dirname, '..', '..');

function read(relativePath) {
  return fs.readFileSync(path.join(rootDir, relativePath), 'utf8');
}

test('changelog page template renders the generated build history fragment', () => {
  const template = read('templates/_entries/changelog-page.twig');
  const generated = read('templates/_generated/changelog.twig');
  const siteCss = read('web/css/site.css');

  assert.match(template, /include\('_generated\/changelog\.twig',\s*ignore_missing\s*=\s*true\)/);
  assert.match(template, /Changelog unavailable/);
  assert.match(template, /generated from conventional commits and git tags/i);
  assert.match(generated, /<div class="container-sections">/);
  assert.match(generated, /<section class="panel panel--padded">/);
  assert.match(generated, /Build Snapshot/);
  assert.match(generated, /Change Types/);
  assert.match(generated, /class="changelog-types changelog-types-v\d+ active"/);
  assert.match(generated, /aria-label="Changelog sections"/);
  assert.match(generated, /href="#feature-changes-v\d+"/);
  assert.match(template, /changelog-types-sidebar/);
  assert.match(template, /typesSidebar\.appendChild/);
  assert.match(generated, /<ul class="list">/);
  assert.match(generated, /<h5>Add reusable colour pairs to archive cards<\/h5>/);
  assert.match(generated, /<span class="caption">/);
  assert.match(generated, /Replace inline footer markup with\s+include &#039;_partials\/site-footer\.twig&#039;/i);
  assert.match(generated, /in index\.twig, category\.twig, and tag\.twig/i);
  assert.match(siteCss, /\.container-actions\s*\{[\s\S]*display:\s*flex;/);
  assert.match(siteCss, /\.container-actions\s*\{[\s\S]*align-items:\s*center;/);
  assert.match(siteCss, /\.panel\s*\{[\s\S]*border:\s*1px solid var\(--border\);/);
  assert.doesNotMatch(siteCss, /\.change-log/);
  assert.doesNotMatch(generated, /\{\%|\{\{|\{#/);
  assert.doesNotMatch(generated, /change-log-list|change-log-item-title|change-log-item-meta/);
  assert.doesNotMatch(siteCss, /\.container-actions h4/);
  assert.doesNotMatch(siteCss, /\.container-actions \.caption/);
  assert.doesNotMatch(generated, /style="margin: 0; flex: 1 1 14rem;"/);
  assert.doesNotMatch(generated, /style="margin: 0;"/);
});

test('changelog page config is wired as a single entry page', () => {
  const sectionConfig = read('config/project/sections/changeLogPage--9bf544f7-7edb-4aae-833f-baffc58075f3.yaml');
  const entryTypeConfig = read('config/project/entryTypes/changeLogPage--7da26bdd-69b5-40b9-8fb9-99992183e3fa.yaml');
  const composer = read('composer.json');
  const generator = read('scripts/GenerateBuildInfo.php');

  assert.match(sectionConfig, /handle:\s*changeLogPage/);
  assert.match(sectionConfig, /type:\s*single/);
  assert.match(sectionConfig, /template:\s*_entries\/changelog-page\.twig/);
  assert.match(sectionConfig, /uriFormat:\s*changelog/);
  assert.match(entryTypeConfig, /name:\s*'Changelog'/);
  assert.match(entryTypeConfig, /showSlugField:\s*false/);
  assert.match(composer, /"build-changelog":\s*"@php scripts\/GenerateBuildInfo\.php --root=\. --output=templates\/_generated\/changelog\.twig --format=twig"/);
  assert.match(composer, /"post-install-cmd":\s*\[\s*"@build-info"\s*\]/s);
  assert.match(generator, /elseif \(\$format === 'twig'\)/);
});
