import esbuild from 'esbuild'

const isDev = process.argv.includes('--dev')

async function compile(options) {
    const context = await esbuild.context(options)

    if (isDev) {
        await context.watch()
    } else {
        await context.rebuild()
        await context.dispose()
    }
}

const defaultOptions = {
    define: {
        'process.env.NODE_ENV': isDev ? `'development'` : `'production'`,
    },
    bundle: true,
    mainFields: ['module', 'main'],
    platform: 'neutral',
    // Must be esm: AlpineComponents are consumed through x-load's dynamic import().
    // An IIFE bundle has an empty namespace, so Alpine.data never registers (silent).
    format: 'esm',
    sourcemap: isDev ? 'inline' : false,
    sourcesContent: isDev,
    treeShaking: true,
    target: ['es2020'],
    minify: !isDev,
    plugins: [{
        name: 'watchPlugin',
        setup: function (build) {
            build.onStart(() => {
                build.initialOptions.entryPoints.forEach((buildFile) => {
                    console.log(`Build started at ${new Date(Date.now()).toLocaleTimeString()}: ${buildFile}`)
                })
            })
            build.onEnd((result) => {
                build.initialOptions.entryPoints.forEach((buildFile) => {
                    if (result.errors.length > 0) {
                        console.log(`Build failed at ${new Date(Date.now()).toLocaleTimeString()}: ${buildFile}`, result.errors)
                    } else {
                        console.log(`Build finished at ${new Date(Date.now()).toLocaleTimeString()}: ${buildFile}`)
                    }
                })
            })
        }
    }],
}

compile({
    ...defaultOptions,
    entryPoints: [
        './resources/js/components/media-manager.js',
        './resources/js/components/picker-field.js',
        './resources/js/components/file-picker-field.js',
    ],
    // Keep the dist/components/*.js layout: esbuild flattens entry points into dist root when
    // their common directory is components.
    outbase: './resources/js/',
    outdir: './resources/dist/',
}).then(() => {
    console.log('Build completed for media components')
})
