const sql = require('mssql');
require('dotenv').config();

const config = {
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    server: process.env.DB_SERVER,
    database: process.env.DB_DATABASE,
    options: {
        encrypt: false, // false se local
        trustServerCertificate: true
    }
};

async function conectar() {
    try {
        const pool = await sql.connect(config);
        return pool;
    } catch (err) {
        console.error('Erro de conexão:', err);
    }
}

module.exports = conectar;
