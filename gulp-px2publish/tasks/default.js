// Generated by CoffeeScript 1.11.1
(function() {
  var border, config, default_tasks, gulp, paths;

  config = require('../config');

  paths = config.paths;

  border = config.border;

  gulp = require('gulp');

  default_tasks = [];

  default_tasks.push('watch');

  console.log("- task -" + border);

  console.log(default_tasks);

  console.log(border);

  gulp.task('default', default_tasks);

}).call(this);
