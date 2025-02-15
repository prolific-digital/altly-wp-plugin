const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const Dotenv = require("dotenv-webpack");
const CopyPlugin = require("copy-webpack-plugin");

module.exports = (env, argv) => {
  const isProd = argv.mode === "production";

  return {
    entry: "./src/index.js",
    output: {
      path: path.resolve(__dirname, "build"),
      filename: "index.js",
      publicPath: "",
    },
    module: {
      rules: [
        {
          test: /\.jsx?$/,
          exclude: /node_modules/,
          use: "babel-loader",
        },
        {
          test: /\.css$/,
          use: [
            isProd ? MiniCssExtractPlugin.loader : "style-loader",
            "css-loader",
            "postcss-loader",
          ],
        },
        {
          test: /\.scss$/,
          use: [
            isProd ? MiniCssExtractPlugin.loader : "style-loader",
            "css-loader",
            "postcss-loader",
            "sass-loader",
          ],
        },
      ],
    },
    resolve: {
      extensions: [".js", ".jsx"],
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: "index.css",
      }),
      new Dotenv({
        path: isProd
          ? path.resolve(__dirname, ".env") // Production builds load .env
          : path.resolve(__dirname, ".env.local"), // Development loads .env.local
        safe: false,
        allowEmptyValues: true,
        systemvars: false,
        silent: false,
      }),
      // Copy all PHP files to the build folder, excluding unnecessary files.
      new CopyPlugin({
        patterns: [
          {
            from: "**/*.php",
            context: __dirname,
            globOptions: {
              ignore: [
                "**/node_modules/**",
                "**/build/**",
                "**/.DS_Store",
                "**/.env",
                "**/.env.local",
                "**/.gitignore",
                "**/*.map",
                "**/src/**",
              ],
            },
          },
        ],
      }),
    ],
    devServer: {
      static: {
        directory: path.join(__dirname, "build"),
      },
      compress: true,
      port: 3002, // your chosen port
      hot: false, // disable hot module replacement
      open: false,
      watchFiles: ["src/**/*"],
      devMiddleware: {
        writeToDisk: true, // write assets to disk in dev mode
      },
      allowedHosts: "all", // Allow any host
    },
    devtool: isProd ? "source-map" : "eval-source-map",
  };
};
