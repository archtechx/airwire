const chokidar = require('chokidar');
const exec = require('child_process').exec;

class AirwireWatcher {
    constructor(files = 'app/**/*.php') {
        this.files = files;
    }

    apply(compiler) {
        compiler.hooks.afterEnvironment.tap('AirwireWatcher', () => {
            chokidar
                .watch(this.files, { usePolling: false, persistent: true })
                .on('change', this.fire);
        });
    }

    fire() {
        exec('php artisan airwire:generate');
        console.log('Rebuilding Airwire definitions');
    }
}

module.exports = AirwireWatcher;
