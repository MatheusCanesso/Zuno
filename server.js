const express = require('express');
const recomendarZuns = require('algoritmoRecomendacao.js');

const app = express();
const PORT = 3000;

app.use(express.json());

app.get('/feed/:usuarioID', async (req, res) => {
    const usuarioID = parseInt(req.params.usuarioID);
    try {
        const feed = await recomendarZuns(usuarioID);
        res.json(feed);
    } catch (err) {
        console.error(err);
        res.status(500).send('Erro ao gerar o feed');
    }
});

app.listen(PORT, () => {
    console.log(`Servidor rodando em http://localhost:${PORT}`);
});
