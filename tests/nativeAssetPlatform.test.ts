import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { test } from 'node:test';

test('native asset markers match the platform NativePHP actually configures', () => {
    for (const [args, expected] of [
        [['--mode=ios'], 'ios'],
        [['--mode=android'], 'android'],
        [['--mode', 'ios'], 'web'],
        [[], 'web'],
    ] as const) {
        const marker = execFileSync(process.execPath, [
            '--input-type=module', '-e', `
                import config from './vite.config.ts';
                const plugin = config.plugins.find(plugin => plugin?.name === 'buff-native-asset-platform');
                plugin.generateBundle.call({ emitFile: asset => process.stdout.write(asset.source) });
            `, '--', ...args,
        ], { cwd: new URL('..', import.meta.url), encoding: 'utf8' });

        assert.equal(marker.trim(), expected);
    }
});
