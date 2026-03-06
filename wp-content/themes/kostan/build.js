const esbuild = require('esbuild');
const { sassPlugin } = require('esbuild-sass-plugin');
const browserSync = require('browser-sync').create();
const os = require('os');

const isDev = process.argv.includes('--watch');

// Get local IP
function getLocalIP() {
  const interfaces = os.networkInterfaces();
  for (const name of Object.keys(interfaces)) {
	for (const iface of interfaces[name]) {
	  if (iface.family === 'IPv4' && !iface.internal) {
		return iface.address;
	  }
	}
  }
  return 'localhost';
}

const config = {
  entryPoints: ['assets/js/main.js', 'assets/css/main.scss'],
  bundle: true,
  minify: !isDev,
  sourcemap: isDev,
  outdir: 'dist',
  loader: {
	'.png': 'file',
	'.jpg': 'file',
	'.jpeg': 'file',
	'.svg': 'file',
	'.woff': 'file',
	'.woff2': 'file',
	'.ttf': 'file',
  },
  publicPath: '/wp-content/themes/Host/dist/',
  plugins: [
	sassPlugin(),
	{
	  name: 'reload-notify',
	  setup(build) {
		build.onEnd(result => {
		  if (result.errors.length === 0 && isDev) {
			const outputs = Object.keys(result.metafile?.outputs || {});
			const hasCSSOnly = outputs.every(file => !file.endsWith('.js') || file.includes('.js.map'));
			
			if (hasCSSOnly) {
			  console.log('💅 CSS updated - injecting...');
			  browserSync.reload('*.css');
			} else {
			  console.log('🔄 Files updated - reloading...');
			  browserSync.reload();
			}
		  }
		});
	  },
	},
  ],
  logLevel: 'info',
  metafile: true,
};

async function build() {
  try {
	if (isDev) {
	  const localIP = getLocalIP();

	  browserSync.init({
		proxy: {
		  target: 'http://villasandhouses.local',
		  proxyReq: [
			function(proxyReq) {
			  // Set headers to handle HTTPS -> HTTP proxy
			  proxyReq.setHeader('X-Forwarded-Proto', 'http');
			}
		  ]
		},
		https: false, // Force HTTP
		host: localIP, // ← Changed from '0.0.0.0' to actual IP
		port: 3000,
		open: false,
		logLevel: 'info', // ← Changed from 'debug' to reduce noise
		notify: {
		  styles: {
			top: 'auto',
			bottom: '0',
			borderRadius: '5px 5px 0 0',
			padding: '8px 15px',
			fontSize: '14px'
		  }
		},
		files: [
		  {
			match: ['**/*.php', '!node_modules/**/*'],
			fn: function(event, file) {
			  if (event === 'change') {
				console.log(`📄 PHP changed: ${file} - reloading...\n`);
				browserSync.reload();
			  }
			}
		  }
		],
		injectChanges: true,
		ghostMode: false,
		ui: { 
		  port: 3001
		},
		logConnections: true,
		logFileChanges: false, // ← Reduce noise
		rewriteRules: [
		  {
			match: /http:\/\/villasandhouses\.local/g,
			fn: function() {
			  return `http://${localIP}:3000`;
			}
		  }
		]
	  });

	  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
	  console.log('🚀 Development server started!');
	  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
	  console.log('📍 WordPress:       https://villasandhouses.local');
	  console.log('🖥️  This Computer:   http://localhost:3000');
	  console.log(`📱 Other Devices:   http://${localIP}:3000`);
	  console.log(`⚙️  UI:              http://localhost:3001`);
	  console.log('\n⚠️  IMPORTANT: Use HTTP (not HTTPS) from other devices!');
	  console.log(`   ✅ From phone/tablet: http://${localIP}:3000`);
	  console.log('\n💡 Troubleshooting:');
	  console.log(`   • Ping test: ping ${localIP}`);
	  console.log(`   • Port test: telnet ${localIP} 3000`);
	  console.log(`   • Or test: curl http://${localIP}:3000`);
	  console.log('\n👀 Watching for changes...\n');
	  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

	  const ctx = await esbuild.context(config);
	  await ctx.watch();

	} else {
	  await esbuild.build(config);
	  console.log('✅ Production build complete!');
	}
  } catch (e) {
	console.error('❌ Build failed:', e);
	process.exit(1);
  }
}

build();