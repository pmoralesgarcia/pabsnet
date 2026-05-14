require('dotenv').config();
const express = require('express');
const mariadb = require('mariadb');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const cors = require('cors');
const helmet = require('helmet'); // Added for security
const app = express();

const SECRET_KEY = process.env.API_JWT_SECRET;
const VALID_TABLES = ['albums', 'songs', 'artists']; // Whitelist for safety

const pool = mariadb.createPool({
    host: process.env.API_DB_HOST2,
    user: process.env.API_DB_USER2,
    password: process.env.API_DB_PASS2,
    database: process.env.API_DB2,
    connectionLimit: 10
});

// Middleware
app.use(helmet());
app.use(cors());
app.use(express.json());
app.use(express.static('public'));

// Validation helper for routes using dynamic tables
const validateTable = (req, res, next) => {
    if (!VALID_TABLES.includes(req.params.mediaType)) {
        return res.status(400).json({ status: "error", message: "Invalid resource type" });
    }
    next();
};

const authenticate = (req, res, next) => {
    const token = req.headers['authorization'];
    if (!token) return res.status(401).json({ status: "error", message: "Unauthorized" });
    try {
        req.user = jwt.verify(token, SECRET_KEY);
        next();
    } catch (err) { res.status(401).json({ status: "error", message: "Session Expired" }); }
};

// --- AUTH ---
app.post('/api/v1/auth/login', async (req, res) => {
    const { username, password } = req.body;
    let conn;
    try {
        conn = await pool.getConnection();
        const rows = await conn.query("SELECT * FROM users WHERE username = ?", [username]);
        if (rows.length > 0 && await bcrypt.compare(password, rows[0].password_hash)) {
            const token = jwt.sign({ id: rows[0].id }, SECRET_KEY, { expiresIn: '24h' });
            return res.json({ status: "success", data: { token } });
        }
        res.status(401).json({ status: "error", message: "Invalid credentials" });
    } catch (err) { res.status(500).json({ status: "error", message: "Login failed" }); } 
    finally { if (conn) conn.end(); }
});

// --- MEDIA CRUD ---
app.get('/api/v1/:mediaType', validateTable, authenticate, async (req, res) => {
    let conn;
    try {
        conn = await pool.getConnection();
        const search = req.query.search || '';
        const rows = await conn.query(`SELECT * FROM ${req.params.mediaType} WHERE title LIKE ? OR artist LIKE ?`, [`%${search}%`, `%${search}%`]);
        res.json({ status: "success", data: rows });
    } catch (err) { res.status(500).json({ status: "error", message: "Fetch failed" }); }
    finally { if (conn) conn.end(); }
});

app.post('/api/v1/:mediaType', validateTable, authenticate, async (req, res) => {
    let conn;
    try {
        conn = await pool.getConnection();
        const fields = Object.keys(req.body);
        const query = `INSERT INTO ${req.params.mediaType} (${fields.join(',')}) VALUES (${fields.map(()=>'?').join(',')})`;
        const result = await conn.query(query, Object.values(req.body));
        res.json({ status: "success", data: { id: Number(result.insertId) } });
    } catch (err) { res.status(400).json({ status: "error", message: "Create failed" }); }
    finally { if (conn) conn.end(); }
});

app.put('/api/v1/:mediaType/:id', validateTable, authenticate, async (req, res) => {
    let conn;
    try {
        conn = await pool.getConnection();
        const fields = Object.keys(req.body);
        const sets = fields.map(f => `${f} = ?`).join(', ');
        await conn.query(`UPDATE ${req.params.mediaType} SET ${sets} WHERE id = ?`, [...Object.values(req.body), req.params.id]);
        res.json({ status: "success", message: "Updated" });
    } catch (err) { res.status(400).json({ status: "error", message: "Update failed" }); }
    finally { if (conn) conn.end(); }
});

app.delete('/api/v1/:mediaType/:id', validateTable, authenticate, async (req, res) => {
    let conn;
    try {
        conn = await pool.getConnection();
        await conn.query(`DELETE FROM ${req.params.mediaType} WHERE id = ?`, [req.params.id]);
        res.json({ status: "success", message: "Deleted" });
    } catch (err) { res.status(400).json({ status: "error", message: "Delete failed" }); }
    finally { if (conn) conn.end(); }
});

// --- PUBLIC READ-ONLY ---
app.get('/api/v1/public/:mediaType', validateTable, async (req, res) => {
    let conn;
    try {
        conn = await pool.getConnection();
        const rows = await conn.query(`SELECT id, title, artist, copyright_year, genre FROM ${req.params.mediaType}`);
        res.json({ status: "success", data: rows });
    } catch (err) { res.status(500).json({ status: "error", message: "Database error" }); }
    finally { if (conn) conn.end(); }
});

app.listen(3000, () => console.log('REST API v1 active on port 3000'));