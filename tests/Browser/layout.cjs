'use strict';

// Run only against the isolated e2e server after tests/Browser/seed.php.
// node tests/Browser/layout.cjs
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = new URL(process.env.LAYOUT_BASE_URL || 'http://127.0.0.1:8081');
if (base.protocol !== 'http:' || !['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)
    || base.username || base.password || base.pathname !== '/' || base.search || base.hash) {
    throw new Error('LAYOUT_BASE_URL must be the origin of the isolated HTTP loopback e2e server.');
}
const artifacts = process.env.LAYOUT_ARTIFACT_DIR || '/workspace/.tg-cache/tg008-browser';
fs.mkdirSync(artifacts, { recursive: true });

const fixtures = {
    password: 'test-only-password',
    client: 'layout-client@example.invalid',
    admin: 'layout-admin@example.invalid',
    longClient: `layout-long-${'x'.repeat(52)}@${'d'.repeat(63)}.example.invalid`,
    longName: `LayoutLong${'NameWithoutSpaces'.repeat(6)}`,
};
const widths = [
    { width: 360, height: 800 },
    { width: 768, height: 1024 },
    { width: 1280, height: 900 },
];
const results = [];
const browserErrors = [];
const screenshots = [];

function safeError(error) {
    return String(error?.message || error)
        .replaceAll(fixtures.password, '[test credential]')
        .replaceAll('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '[public RFC fixture]')
        .replaceAll('12345678901234567890', '[public RFC fixture]')
        .replace(/\b\d{6}\b/g, '[redacted code]')
        .slice(0, 900);
}

async function check(view, name, callback) {
    try {
        await callback();
        results.push({ view, check: name, status: 'passed' });
    } catch (error) {
        results.push({ view, check: name, status: 'failed', error: safeError(error) });
    }
}

// RFC 6238 SHA-1, 30-second counter, 6 digits. Never print the generated code.
function authenticatorCode() {
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const digest = crypto.createHmac('sha1', Buffer.from('12345678901234567890')).update(counter).digest();
    const offset = digest[digest.length - 1] & 15;
    return ((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).toString().padStart(6, '0');
}

async function navigate(page, uri) {
    const response = await page.goto(new URL(uri, base).href, { waitUntil: 'networkidle' });
    assert.equal(response?.status(), 200, `Expected successful page response for ${uri}`);
    await page.locator('main#konten').waitFor({ state: 'visible' });
    await page.waitForFunction(() => Boolean(window.Livewire));
}

async function login(page, email, admin = false) {
    await navigate(page, '/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(fixtures.password);
    await Promise.all([
        page.waitForURL(admin ? '**/admin/2fa/challenge' : '**/member'),
        page.locator('form[wire\\:submit="submit"] button[type="submit"]').click(),
    ]);
    if (admin) {
        await page.locator('#otp').waitFor({ state: 'visible' });
        await page.locator('#otp').fill(authenticatorCode());
        await Promise.all([
            page.waitForURL('**/admin'),
            page.locator('form[wire\\:submit="submit"] button[type="submit"]').click(),
        ]);
    }
    await page.waitForLoadState('networkidle');
}

async function inspectLayout(page, view) {
    await check(view, 'one keyboard-accessible main and skip link', async () => {
        assert.equal(await page.locator('main').count(), 1);
        assert.equal(await page.locator('main#konten').getAttribute('tabindex'), '-1');
        const skip = page.locator('a.skip-link[href="#konten"]');
        assert.equal(await skip.count(), 1);
        await skip.focus();
        assert.equal(await skip.evaluate(el => el === document.activeElement), true);
        const bounds = await skip.boundingBox();
        assert.ok(bounds && bounds.y >= 0 && bounds.height > 0, 'Focused skip link must be on screen');
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('main#konten').evaluate(el => el === document.activeElement), true);
    });

    await check(view, 'light theme remains active with dark system preference', async () => {
        const theme = await page.evaluate(() => {
            const root = getComputedStyle(document.documentElement);
            const probe = document.createElement('span');
            probe.style.cssText = 'background-color:var(--color-bone);position:absolute;visibility:hidden';
            document.body.append(probe);
            const expected = getComputedStyle(probe).backgroundColor;
            probe.remove();
            return {
                meta: document.querySelector('meta[name="color-scheme"]')?.content,
                scheme: root.colorScheme,
                background: root.backgroundColor,
                expected,
                palette: ['forest', 'lime', 'bone', 'coral'].map(name => root.getPropertyValue(`--color-${name}`).trim()),
                darkSystem: matchMedia('(prefers-color-scheme: dark)').matches,
            };
        });
        assert.equal(theme.darkSystem, true);
        assert.equal(theme.meta, 'light');
        assert.equal(theme.scheme, 'light');
        assert.equal(theme.background, theme.expected);
        assert.ok(theme.palette.every(Boolean), 'All four documented theme colors must be defined');
    });

    await check(view, 'no horizontal overflow in document or visible containers', async () => {
        const overflow = await page.evaluate(() => {
            const width = document.documentElement.clientWidth;
            const result = [];
            if (document.documentElement.scrollWidth > width + 2) result.push('document');
            for (const el of document.querySelectorAll('header,main,section,article,nav,div,form,ul,li')) {
                const rect = el.getBoundingClientRect();
                const style = getComputedStyle(el);
                if (rect.width < 1 || rect.height < 1 || style.visibility === 'hidden' || style.display === 'none') continue;
                if (rect.left < -2 || rect.right > width + 2 || el.scrollWidth > el.clientWidth + 2) {
                    result.push(`${el.tagName.toLowerCase()}${el.id ? `#${el.id}` : ''}.${String(el.className).split(' ').slice(0, 2).join('.')}`);
                }
            }
            return [...new Set(result)].slice(0, 16);
        });
        assert.deepEqual(overflow, [], 'Visible layout containers overflow horizontally');
    });

    await check(view, 'current page is identified in primary navigation', async () => {
        const active = await page.evaluate(() => {
            const toggle = document.querySelector('[data-nav-toggle]');
            const nav = document.getElementById(toggle?.getAttribute('aria-controls'));
            const samePage = [...(nav?.querySelectorAll('a[href]') || [])].filter(link => {
                const target = new URL(link.href, location.href);
                return target.pathname === location.pathname && !target.hash;
            });
            return samePage.some(link => link.getAttribute('aria-current') === 'page');
        });
        assert.equal(active, true, 'Navigation must mark this page with aria-current=page');
    });

    if (page.viewportSize().width < 1024) {
        await check(view, 'mobile action targets are at least 44px', async () => {
            const small = await page.evaluate(() => {
                const selectors = 'button, .button, .auth-secondary, header a, nav a';
                return [...document.querySelectorAll(selectors)].filter(el => {
                    const box = el.getBoundingClientRect();
                    return box.width > 0 && box.height > 0 && getComputedStyle(el).visibility !== 'hidden'
                        && (box.width < 43.5 || box.height < 43.5);
                }).map(el => `${el.tagName.toLowerCase()}.${String(el.className).split(' ').slice(0, 2).join('.')}`);
            });
            assert.deepEqual(small, [], 'Visible primary navigation/action targets must be at least 44px');
        });
    }
}

async function inspectNavigation(page, view) {
    const original = page.url();
    await check(view, 'responsive navigation, Escape and link-close behavior', async () => {
        const toggle = page.locator('[data-nav-toggle]');
        assert.equal(await toggle.count(), 1);
        const controls = await toggle.getAttribute('aria-controls');
        assert.ok(controls, 'Menu button must reference the navigation');
        const nav = page.locator(`[id=${JSON.stringify(controls)}]`);
        assert.equal(await nav.count(), 1);

        if (page.viewportSize().width >= 1024) {
            assert.equal(await toggle.isVisible(), false);
            assert.equal(await nav.isVisible(), true);
            return;
        }

        assert.equal(await toggle.isVisible(), true);
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await nav.isVisible(), false);
        assert.equal(await nav.locator('a').evaluateAll(links => links.some(link => {
            link.focus();
            return document.activeElement === link;
        })), false, 'Closed navigation must not accept focus');

        await toggle.click();
        await page.waitForFunction(() => document.querySelector('[data-nav-toggle]')?.getAttribute('aria-expanded') === 'true');
        assert.equal(await nav.isVisible(), true);
        const openTargets = await nav.locator('a').evaluateAll(links => links.map(link => {
            const box = link.getBoundingClientRect();
            return box.width >= 43.5 && box.height >= 43.5;
        }));
        assert.ok(openTargets.length > 0 && openTargets.every(Boolean), 'Open mobile navigation targets must be at least 44px');

        if (await nav.getAttribute('data-drawer') !== null) {
            assert.equal(await page.locator('.portal-workspace').evaluate(el => el.inert), true);
            const focusable = nav.locator('a[href], button:not([disabled])');
            await focusable.last().focus();
            await page.keyboard.press('Tab');
            assert.equal(await focusable.first().evaluate(el => el === document.activeElement), true, 'Tab must stay inside the open drawer');
            await page.keyboard.press('Shift+Tab');
            assert.equal(await focusable.last().evaluate(el => el === document.activeElement), true, 'Shift+Tab must stay inside the open drawer');
        }

        if (page.viewportSize().width === 360) {
            const file = `${view.split(':')[0]}-${new URL(original).pathname.replaceAll('/', '-').replace(/^-/, '') || 'home'}-360-menu.png`;
            await page.screenshot({ path: path.join(artifacts, file), fullPage: true });
            screenshots.push({ view: `${view}:menu-open`, file });
        }

        await nav.locator('a').first().focus();
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.querySelector('[data-nav-toggle]')?.getAttribute('aria-expanded') === 'false');
        assert.equal(await nav.isVisible(), false);
        // Alpine restores focus after its reactive DOM update, including inert removal.
        await page.waitForFunction(() => document.activeElement === document.querySelector('[data-nav-toggle]'), null, { timeout: 2000 });
        assert.equal(await toggle.evaluate(el => el === document.activeElement), true, 'Escape must restore focus to menu button');

        await toggle.click();
        await nav.locator('a').first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForFunction(() => document.querySelector('[data-nav-toggle]')?.getAttribute('aria-expanded') === 'false');
        const nextToggle = page.locator('[data-nav-toggle]');
        const nextNav = page.locator(`[id=${JSON.stringify(await nextToggle.getAttribute('aria-controls'))}]`);
        assert.equal(await nextNav.isVisible(), false, 'Navigation must close after following a link');
    });
    if (page.url() !== original) await navigate(page, new URL(original).pathname);
}

async function inspectAdminSearch(page, view) {
    await check(view, 'long fixture text wraps and Livewire search preserves navigation', async () => {
        assert.ok(await page.locator('.auth-client-row h3').allTextContents().then(names => names.includes(fixtures.longName)));
        await page.locator('#search').fill('LayoutLong');
        await page.waitForFunction(name => {
            const names = [...document.querySelectorAll('.auth-client-row h3')].map(el => el.textContent.trim());
            return names.length === 1 && names[0] === name;
        }, fixtures.longName);
        assert.equal(await page.locator('[aria-current="page"][href$="/admin/client"]').count(), 1);
        if (page.viewportSize().width < 1024) {
            await page.locator('[data-nav-toggle]').click();
            await page.waitForFunction(() => document.querySelector('[data-nav-toggle]')?.getAttribute('aria-expanded') === 'true');
            await page.keyboard.press('Escape');
            await page.waitForFunction(() => document.querySelector('[data-nav-toggle]')?.getAttribute('aria-expanded') === 'false');
        }
        await page.locator('#search').fill('');
        await page.waitForFunction(() => document.querySelectorAll('.auth-client-row').length === 2);
    });
}

async function run() {
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });
    try {
        const roles = [
            { name: 'guest', pages: ['/', '/login', '/register'] },
            { name: 'client', email: fixtures.client, pages: ['/member', '/member/profil'] },
            { name: 'admin', email: fixtures.admin, admin: true, pages: ['/admin', '/admin/client'] },
            { name: 'long-client', email: fixtures.longClient, pages: ['/member'] },
        ];
        for (const role of roles) {
            const context = await browser.newContext({ viewport: widths[2], colorScheme: 'dark' });
            const page = await context.newPage();
            page.setDefaultTimeout(12000);
            page.setDefaultNavigationTimeout(20000);
            let currentView = `${role.name}:authentication`;
            page.on('pageerror', error => browserErrors.push({ view: currentView, type: 'pageerror', error: safeError(error) }));
            page.on('console', message => {
                if (message.type() === 'error') browserErrors.push({ view: currentView, type: 'console', error: safeError(message.text()) });
            });
            try {
                if (role.email) await login(page, role.email, role.admin);
                for (const viewport of widths) {
                    await page.setViewportSize(viewport);
                    for (const uri of role.pages) {
                        currentView = `${role.name}:${uri}:${viewport.width}`;
                        try {
                            await navigate(page, uri);
                            await inspectLayout(page, currentView);
                            if (uri === '/admin/client') await inspectAdminSearch(page, currentView);
                            await inspectNavigation(page, currentView);
                            // Never capture the TOTP challenge, setup secrets, or password-result pages.
                            assert.equal(new URL(page.url()).pathname, uri);
                            const file = `${role.name}-${uri.replaceAll('/', '-').replace(/^-/, '') || 'home'}-${viewport.width}.png`;
                            await page.screenshot({ path: path.join(artifacts, file), fullPage: true });
                            screenshots.push({ view: currentView, file });
                        } catch (error) {
                            results.push({ view: currentView, check: 'page navigation or screenshot', status: 'failed', error: safeError(error) });
                        }
                    }
                }
            } catch (error) {
                results.push({ view: currentView, check: 'real UI authentication', status: 'failed', error: safeError(error) });
            } finally {
                await context.close();
            }
        }
    } finally {
        await browser.close();
    }
    const summary = {
        workflow: 'TG-008 responsive layouts using real UI authentication and isolated synthetic fixtures',
        passed: results.filter(result => result.status === 'passed').length,
        failed: results.filter(result => result.status === 'failed').length,
        browserErrors,
        screenshots,
        results,
    };
    fs.writeFileSync(path.join(artifacts, 'summary.json'), `${JSON.stringify(summary, null, 2)}\n`);
    process.stdout.write(`${JSON.stringify(summary, null, 2)}\n`);
    if (summary.failed > 0 || browserErrors.length > 0) process.exitCode = 1;
}

run().catch(error => {
    process.stderr.write(`${safeError(error)}\n`);
    process.exitCode = 1;
});
