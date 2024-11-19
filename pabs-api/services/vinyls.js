const db = require('./db');
const helper = require('../helper');
const config = require('../config');

async function getMultiple(page = 1){
  const offset = helper.getOffset(page, config.listPerPage);
  const rows = await db.query(
    `SELECT id, title, artist, copyright_year, label, isbn, catalog_number, info_url, thumbnail_url, notes
    FROM vinyls LIMIT ${offset},${config.listPerPage}`
  );
  const data = helper.emptyOrRows(rows);
  const meta = {page};

  return {
    data,
    meta
  }
}

async function create(vinyl){
  const result = await db.query(
    `INSERT INTO vinyls 
    (title, artist, copyright_year, label, isbn, catalog_number, info_url, thumbnail_url, notes) 
    VALUES 
    ('${vinyl.title}', ${vinyl.artist}, ${vinyl.copyright_year}, ${vinyl.label}, ${vinyl.isbn}, ${vinyl.catalog_number}, ${vinyl.info_url}, ${vinyl.thumbnail_url}, ${vinyl.notes}
)`
  );

  let message = 'Error in creating vinyl';

  if (result.affectedRows) {
    message = 'vinyl created successfully';
  }

  return {message};
}

async function update(id, programmingLanguage){
  const result = await db.query(
    `UPDATE vinyls
    SET title="${vinyl.title}", artist=${vinyl.artist}, copyright_year=${vinyl.copyright_year}, label=${vinyl.label}, isbn=${vinyl.isbn}, catalog_number=${vinyl.catalog_number}, info_url=${vinyl.info_url}, thumbnail_url=${vinyl.thumbnail_url}, notes=${vinyl.notes}
 
    WHERE id=${id}` 
  );

  let message = 'Error in updating vinyl';

  if (result.affectedRows) {
    message = 'vinyl updated successfully';
  }

  return {message};
}

async function remove(id){
  const result = await db.query(
    `DELETE FROM vinyls WHERE id=${id}`
  );

  let message = 'Error in deleting vinyl';

  if (result.affectedRows) {
    message = 'vinyl deleted successfully';
  }

  return {message};
}

async function search(id){
  const rows = await db.callSpVinylSearch(id);
  const data = helper.emptyOrRows(rows);
  return {
    data
  }
}

module.exports = {
  getMultiple,
  create,
  update,
  remove,
  search
}
