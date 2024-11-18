const mysql = require("mysql2/promise");
const config = require("../config");

async function query(sql, params) {
  const connection = await mysql.createConnection(config.db);
  const [results] = await connection.execute(sql, params);

  return results;
}

async function callSpSearch(id) {
  const connection = await mysql.createConnection(config.db);
  const [results] = await connection.query(
    "SELECT name, githut_rank, pypl_rank, tiobe_rank, created_at FROM programming_languages where id = " + id + ""
  );

  return results;
}

async function callSpVinylSearch(id) {
  const connection = await mysql.createConnection(config.db);
  const [results, ] = await connection.query(
    "SELECT title, artist, copyright_year, label, isbn, catalog_number, info_url, thumbnail_url, notes FROM vinyls where id = " + id + ""
  );

  return results;
}

module.exports = {
  query,
  callSpSearch,
  callSpVinylSearch
};
