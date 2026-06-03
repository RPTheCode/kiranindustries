module.exports = {
  apps: [
    {
      name: 'kiran-queue-worker',
      script: 'artisan',
      args: 'queue:work --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      error_file: './storage/logs/pm2-queue-error.log',
      out_file: './storage/logs/pm2-queue-out.log',
    },
    {
      name: 'kiran-scheduler',
      script: 'artisan',
      args: 'schedule:work',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      error_file: './storage/logs/pm2-scheduler-error.log',
      out_file: './storage/logs/pm2-scheduler-out.log',
    }
  ]
};
