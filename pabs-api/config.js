// server.js
const dotenv = require('dotenv');
dotenv.config();
const config = {
  db: {
    /* don't expose password or any sensitive info, done only for demo */
    host: process.env.API_DB_HOST,
    user: process.env.API_DB_USER,
    password: process.env.API_DB_PASS,
    database: process.env.API_DB,
    connectTimeout: 60000,
    multipleStatements: true
  },
  listPerPage: 10
};
module.exports = config;
