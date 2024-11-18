const express = require("express");
const router = express.Router();
const vinyls = require("../services/vinyls");

/* GET vinyls. */
router.get("/", async function (req, res, next) {
  try {
    res.json(await vinyls.getMultiple(req.query.page));
  } catch (err) {
    console.error(`Error while getting vinyls `, err.message);
    next(err);
  }
});

/* POST vinyl */
router.post("/", async function (req, res, next) {
  try {
    res.json(await vinyls.create(req.body));
  } catch (err) {
    console.error(`Error while creating vinyl`, err.message);
    next(err);
  }
});

/* PUT vinyl */
router.put("/:id", async function (req, res, next) {
  try {
    res.json(await vinyls.update(req.params.id, req.body));
  } catch (err) {
    console.error(`Error while updating vinyl`, err.message);
    next(err);
  }
});

/* DELETE vinyl */
router.delete("/:id", async function (req, res, next) {
  try {
    res.json(await vinyls.remove(req.params.id));
  } catch (err) {
    console.error(`Error while deleting vinyl`, err.message);
    next(err);
  }
});

router.get("/:id", async function (req, res, next) {
  try {
    res.json(await vinyls.search(req.params.id));
  } catch (err) {
    console.error(`Error while searching vinyls `, err.message);
    next(err);
  }
});
module.exports = router;
