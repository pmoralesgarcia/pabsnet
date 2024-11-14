// server.js
const dotenv = require('dotenv');
dotenv.config();

const config = {
  db: {
    /* don't expose password or any sensitive info, done only for demo */
    host: "mariadb",
    user: "library_pabs",
    password: process.env.LISTS,
    database: "lists",
    connectTimeout: 60000,
    multipleStatements: true
  },
  listPerPage: 10
};
module.exports = config;
