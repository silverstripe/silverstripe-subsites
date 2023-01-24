const Path = require('path');
const { JavascriptWebpackConfig, CssWebpackConfig } = require('@silverstripe/webpack-config');

const ENV = process.env.NODE_ENV;
const PATHS = {
  ROOT: Path.resolve(),
  SRC: Path.resolve('client/src'),
  DIST: Path.resolve('client/dist'),
};

const config = [
  // Main JS bundle
  new JavascriptWebpackConfig('js', PATHS, 'silverstripe/subsites')
    .setEntry({
      LeftAndMain_Subsites: `${PATHS.SRC}/js/LeftAndMain_Subsites.js`,
      SubsitesTreeDropdownField: `${PATHS.SRC}/js/SubsitesTreeDropdownField.js`
    })
    .getConfig(),
  // sass to css
  new CssWebpackConfig('css', PATHS)
    .setEntry({
      LeftAndMain_Subsites: `${PATHS.SRC}/styles/LeftAndMain_Subsites.scss`
    })
    .getConfig(),
];

module.exports = config;
