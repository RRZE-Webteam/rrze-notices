#!/usr/bin/env node

/**
 * Bumps the version in package.json and synchronizes it into
 * the plugin header (rrze-notices.php) and readme.txt.
 *
 * Usage: node scripts/build-version.js dev|prod|release|major
 *
 * dev     — 4.0.0 → 4.0.0-1 → 4.0.0-2   (prerelease counter)
 * prod    — 4.0.0-5 → 4.0.1              (patch bump, drop prerelease)
 * release — 4.0.0-5 → 4.1.0              (minor bump, reset patch)
 * major   — 4.0.0-5 → 5.0.0              (major bump, reset minor+patch)
 */

var fs = require('fs');
var path = require('path');

var ROOT = path.resolve(__dirname, '..');
var PACKAGE_JSON = path.join(ROOT, 'package.json');
var PLUGIN_FILE = path.join(ROOT, 'rrze-notices.php');
var README_TXT = path.join(ROOT, 'readme.txt');

// --- Version parsing and bumping ---

function parseSemver(version) {
    var m = version.match(/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?$/);
    if (!m) {
        throw new Error('Invalid semver: ' + version);
    }
    return {
        major: parseInt(m[1], 10),
        minor: parseInt(m[2], 10),
        patch: parseInt(m[3], 10),
        prerelease: m[4] || ''
    };
}

function formatSemver(v) {
    var base = v.major + '.' + v.minor + '.' + v.patch;
    return v.prerelease ? base + '-' + v.prerelease : base;
}

function bumpDev(version) {
    var v = parseSemver(version);
    var n = v.prerelease.match(/^(\d+)$/);
    v.prerelease = String(n ? parseInt(n[1], 10) + 1 : 1);
    return formatSemver(v);
}

function bumpProd(version) {
    var v = parseSemver(version);
    v.prerelease = '';
    v.patch++;
    return formatSemver(v);
}

function bumpRelease(version) {
    var v = parseSemver(version);
    v.prerelease = '';
    v.minor++;
    v.patch = 0;
    return formatSemver(v);
}

function bumpMajor(version) {
    var v = parseSemver(version);
    v.prerelease = '';
    v.major++;
    v.minor = 0;
    v.patch = 0;
    return formatSemver(v);
}

function getNextVersion(mode, current) {
    switch (mode) {
        case 'dev': return bumpDev(current);
        case 'prod': return bumpProd(current);
        case 'release': return bumpRelease(current);
        case 'major': return bumpMajor(current);
        default: throw new Error('Unknown mode: ' + mode);
    }
}

// --- File synchronization ---

function fail(message) {
    console.error('[build-version] ' + message);
    process.exit(1);
}

function replaceOrFail(content, pattern, replacement, file, label) {
    if (!pattern.test(content)) {
        fail('Could not find "' + label + '" in ' + file);
    }
    return content.replace(pattern, replacement);
}

function syncPluginFile(version, compat) {
    if (!fs.existsSync(PLUGIN_FILE)) {
        return;
    }
    var plugin = fs.readFileSync(PLUGIN_FILE, 'utf8');

    plugin = replaceOrFail(plugin,
        /Version:\s*[^\r\n]+/,
        'Version:         ' + version,
        PLUGIN_FILE, 'Version');

    if (compat.wprequires) {
        plugin = replaceOrFail(plugin,
            /Requires at least:\s*[^\r\n]+/,
            'Requires at least: ' + compat.wprequires,
            PLUGIN_FILE, 'Requires at least');
        plugin = replaceOrFail(plugin,
            /const RRZE_WP_VERSION\s*=\s*'[^']*';/,
            "const RRZE_WP_VERSION = '" + compat.wprequires + "';",
            PLUGIN_FILE, 'RRZE_WP_VERSION');
    }

    if (compat.phprequires) {
        plugin = replaceOrFail(plugin,
            /Requires PHP:\s*[^\r\n]+/,
            'Requires PHP:      ' + compat.phprequires,
            PLUGIN_FILE, 'Requires PHP');
        plugin = replaceOrFail(plugin,
            /const RRZE_PHP_VERSION\s*=\s*'[^']*';/,
            "const RRZE_PHP_VERSION = '" + compat.phprequires + "';",
            PLUGIN_FILE, 'RRZE_PHP_VERSION');
    }

    plugin = replaceOrFail(plugin,
        /define\('RRZE_NOTICES_VERSION',\s*'[^']*'\);/,
        "define('RRZE_NOTICES_VERSION', '" + version + "');",
        PLUGIN_FILE, 'RRZE_NOTICES_VERSION');

    fs.writeFileSync(PLUGIN_FILE, plugin);
}

function syncReadme(version, compat) {
    if (!fs.existsSync(README_TXT)) {
        return;
    }
    var readme = fs.readFileSync(README_TXT, 'utf8');

    readme = replaceOrFail(readme,
        /Stable tag:\s*[^\r\n]+/,
        'Stable tag: ' + version,
        README_TXT, 'Stable tag');

    if (compat.wprequires) {
        readme = replaceOrFail(readme,
            /Requires at least:\s*[^\r\n]+/,
            'Requires at least: ' + compat.wprequires,
            README_TXT, 'Requires at least');
    }

    if (compat.wptestedup) {
        readme = replaceOrFail(readme,
            /Tested up to:\s*[^\r\n]+/,
            'Tested up to: ' + compat.wptestedup,
            README_TXT, 'Tested up to');
    }

    if (compat.phprequires) {
        readme = replaceOrFail(readme,
            /Requires PHP:\s*[^\r\n]+/,
            'Requires PHP: ' + compat.phprequires,
            README_TXT, 'Requires PHP');
    }

    fs.writeFileSync(README_TXT, readme);
}

// --- Main ---

function main() {
    var mode = process.argv[2];
    if (!['dev', 'prod', 'release', 'major'].includes(mode)) {
        console.error('Usage: node scripts/build-version.js dev|prod|release|major');
        process.exit(1);
    }

    var pkg = JSON.parse(fs.readFileSync(PACKAGE_JSON, 'utf8'));
    var current = pkg.version;
    var next = getNextVersion(mode, current);

    pkg.version = next;
    fs.writeFileSync(PACKAGE_JSON, JSON.stringify(pkg, null, 4) + '\n');

    var compat = pkg.compatibility || {};
    syncPluginFile(next, compat);
    syncReadme(next, compat);

    console.log('[build-version] ' + mode + ': ' + current + ' -> ' + next);
}

main();