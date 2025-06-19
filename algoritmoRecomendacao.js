const conectar = require('  db.js');

async function recomendarZuns(usuarioID) {
    const db = await conectar();

    // 1. Buscar quem o usuário segue
    const { recordset: seguidos } = await db.query(`
        SELECT SeguidoID FROM Conexoes WHERE SeguidorID = ${usuarioID}
    `);
    const idsSeguidos = seguidos.map(r => r.SeguidoID).join(',') || '0';

    // 2. Zuns dos seguidos
    const { recordset: zunsSeguidos } = await db.query(`
        SELECT * FROM Zuns WHERE UsuarioID IN (${idsSeguidos}) AND Visibilidade = 'publico'
    `);

    // 3. Zuns curtidos pelo usuário
    const { recordset: zunsCurtidos } = await db.query(`
        SELECT Zuns.* FROM Zuns
        INNER JOIN ZunLikes ON Zuns.ZunID = ZunLikes.ZunID
        WHERE ZunLikes.UsuarioID = ${usuarioID}
    `);

    // 4. Zuns populares
    const { recordset: zunsPopulares } = await db.query(`
        SELECT Zuns.*, COUNT(ZunLikes.ZunID) AS Popularidade
        FROM Zuns
        LEFT JOIN ZunLikes ON Zuns.ZunID = ZunLikes.ZunID
        GROUP BY Zuns.ZunID, Zuns.UsuarioID, Zuns.Conteudo, Zuns.DataCriacao, Zuns.Visibilidade
        ORDER BY Popularidade DESC
    `);

    // 5. Unir todos os zuns
    const todosZuns = [...zunsSeguidos, ...zunsCurtidos, ...zunsPopulares];
    const mapZuns = new Map();

    todosZuns.forEach(z => {
        const score =
            (seguidos.some(s => s.SeguidoID === z.UsuarioID) ? 5 : 0) +
            (zunsCurtidos.some(c => c.ZunID === z.ZunID) ? 3 : 0) +
            (z.Popularidade ? Math.min(z.Popularidade, 5) : 1);

        if (!mapZuns.has(z.ZunID) || mapZuns.get(z.ZunID).score < score) {
            mapZuns.set(z.ZunID, { ...z, score });
        }
    });

    const zunsOrdenados = Array.from(mapZuns.values()).sort((a, b) => b.score - a.score);
    return zunsOrdenados.slice(0, 20); // Top 20 zuns
}

module.exports = recomendarZuns;
