const express = require("express");
const app = express();
const port = 3000;
const programmingLanguagesRouter = require("./routes/programmingLanguages");
const vinyls = require("./routes/vinyls");

app.use(express.json());
app.use(
  express.urlencoded({
    extended: true,
  })
);
app.get("/", (req, res) => {
  res.json({ message: "ok" });
});
app.use("/programming-languages", programmingLanguagesRouter);
app.use("/vinyls", vinyls);

/* Error handler middleware */
app.use((err, req, res, next) => {
  const statusCode = err.statusCode || 500;
  console.error(err.message, err.stack);
  res.status(statusCode).json({ message: err.message });
  return;
});
app.listen(port, () => {
  console.log(`API app listening at http://localhost:${port}`);
});