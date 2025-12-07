const express = require('express');
const router = express.Router();
const cds = require('../services/cds');

/* GET cds. */
router.get('/', async function(req, res, next) {
  try {
    res.json(await cds.getMultiple(req.query.page));
  } catch (err) {
    console.error(`Error while getting cds `, err.message);
    next(err);
  }
});

/* POST cd */
router.post('/', async function(req, res, next) {
  try {
    res.json(await cds.create(req.body));
  } catch (err) {
    console.error(`Error while creating cd`, err.message);
    next(err);
  }
});

/* PUT cd */
router.put('/:id', async function(req, res, next) {
  try {
    res.json(await cds.update(req.params.id, req.body));
  } catch (err) {
    console.error(`Error while updating cd`, err.message);
    next(err);
  }
});

/* DELETE cd */
router.delete('/:id', async function(req, res, next) {
  try {
    res.json(await cds.remove(req.params.id));
  } catch (err) {
    console.error(`Error while deleting cd`, err.message);
    next(err);
  }
});

router.get('/:id', async function(req, res, next) {
  try {
    res.json(await cds.search(req.params.id));
  } catch (err) {
    console.error(`Error while searching cds `, err.message);
    next(err);
  }
});

module.exports = router;