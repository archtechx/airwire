const exec = require('child_process').exec;

class AirwireWatcher {
    constructor(chodikar, files = 'app/**/*.php') {
        this.chodikar = chodikar;
        this.files = files;
    }

    apply(compiler) {
        compiler.hooks.afterEnvironment.tap('AirwireWatcher', () => {
            this.chodikar
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
