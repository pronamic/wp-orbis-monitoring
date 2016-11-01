module.exports = function( grunt ) {
	require( 'load-grunt-tasks' )( grunt );

	// Project configuration.
	grunt.initConfig( {
		// Package
		pkg: grunt.file.readJSON( 'package.json' ),

		// PHPLint
		phplint: {
			options: {
				phpArgs: {
					'-lf': null
				}
			},
			all: [ 'classes/**/*.php' ]
		},

		// PHP Code Sniffer
		phpcs: {
			application: {
				src: [
					'**/*.php',
					'!bower_components/**',
					'!deploy/**',
					'!node_modules/**'
				],
			},
			options: {
				standard: 'phpcs.ruleset.xml',
				showSniffCodes: true
			}
		},
		
		// Check WordPress version
		checkwpversion: {
			options: {
				readme: 'readme.txt',
				plugin: 'orbis-monitoring.php',
			},
			check: {
				version1: 'plugin',
				version2: 'readme',
				compare: '=='
			},
			check2: {
				version1: 'plugin',
				version2: '<%= pkg.version %>',
				compare: '=='
			}
		},
		
		// Make POT
		makepot: {
			target: {
				options: {
					domainPath: 'languages',
					type: 'wp-plugin',
					updatePoFiles: true,
					updateTimestamp: false,
					exclude: [
						'bower_components/.*',
						'build/.*',
						'deploy/.*',
						'node_modules/.*',
						'vendor/.*'
					]

				}
			}
		}
	} );

	// Default task(s).
	grunt.registerTask( 'default', [ 'phplint', 'checkwpversion', 'makepot' ] );
};
