const db = require('./db');
const helper = require('../helper');
const config = require('../config');

async function getMultiple(page = 1){
  const offset = helper.getOffset(page, config.listPerPage);
  const rows = await db.query(
    `SELECT id, title, artist, copyright_year, label, isbn, catalog_number, info_url, thumbnail_url, notes
    FROM cds LIMIT ${offset},${config.listPerPage}`
  );
  const data = helper.emptyOrRows(rows);
  const meta = {page};

  return {
    data,
    meta
  }
}

async function create(cd){
  const result = await db.query(
    `INSERT INTO cds 
    (title, artist, copyright_year, label, isbn, catalog_number, info_url, thumbnail_url, notes) 
    VALUES 
    ('${cd.title}', ${cd.artist}, ${cd.copyright_year}, ${cd.label}, ${cd.isbn}, ${cd.catalog_number}, ${cd.info_url}, ${cd.thumbnail_url}, ${cd.notes}
)`
  );

  let message = 'Error in creating cd';

  if (result.affectedRows) {
    message = 'cd created successfully';
  }

  return {message};
}

async function update(id, cd){
  const result = await db.query(
    `UPDATE cds
    SET title="${cd.title}", artist=${cd.artist}, copyright_year=${cd.copyright_year}, label=${cd.label}, isbn=${cd.isbn}, catalog_number=${cd.catalog_number}, info_url=${cd.info_url}, thumbnail_url=${cd.thumbnail_url}, notes=${cd.notes}
 
    WHERE id=${id}` 
  );

  let message = 'Error in updating cd';

  if (result.affectedRows) {
    message = 'cd updated successfully';
  }

  return {message};
}

async function remove(id){
  const result = await db.query(
    `DELETE FROM cds WHERE id=${id}`
  );

  let message = 'Error in deleting cd';

  if (result.affectedRows) {
    message = 'cd deleted successfully';
  }

  return {message};
}

async function search(id){
  // Assuming the stored procedure name also changes to reflect CDs
  const rows = await db.callSpCdSearch(id);
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