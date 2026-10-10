/**
 * Preview perhitungan pinjaman di form.
 * HARUS sama dengan hitungPinjaman() di includes/pinjaman_helper.php.
 */
function hitungJumlahCicilan(tenorBulan, frekuensi) {
    tenorBulan = parseInt(tenorBulan, 10) || 0;
    return frekuensi === 'mingguan' ? tenorBulan * 4 : tenorBulan;
}

function hitungPinjaman(pokok, bungaPersen, tenorBulan, frekuensi, skema) {
    pokok = parseFloat(pokok) || 0;
    bungaPersen = parseFloat(bungaPersen) || 0;
    tenorBulan = parseInt(tenorBulan, 10) || 0;
    frekuensi = frekuensi === 'mingguan' ? 'mingguan' : 'bulanan';
    skema = skema === 'tahunan' ? 'tahunan' : 'flat';
    var n = Math.max(1, hitungJumlahCicilan(tenorBulan, frekuensi));
    var rincian = [];
    var totalBunga, total, i;

    if (skema === 'tahunan') {
        totalBunga = pokok * (bungaPersen / 100) * (tenorBulan / 12);
        total = pokok + totalBunga;
        for (i = 1; i <= n; i++) {
            rincian.push({ ke: i, pokok: pokok / n, bunga: totalBunga / n, jumlah: total / n });
        }
    } else {
        totalBunga = Math.round(pokok * bungaPersen / 100);
        total = pokok + totalBunga;
        var cicilan = Math.round(total / n);
        var pokokCicilan = Math.round(pokok / n);
        var sumJumlah = 0, sumPokok = 0;
        for (i = 1; i <= n; i++) {
            var jumlah = i < n ? cicilan : total - sumJumlah;
            var pokokI = i < n ? pokokCicilan : pokok - sumPokok;
            sumJumlah += jumlah;
            sumPokok += pokokI;
            rincian.push({ ke: i, pokok: pokokI, bunga: jumlah - pokokI, jumlah: jumlah });
        }
    }

    return {
        pokok: pokok, bungaPersen: bungaPersen, skema: skema, frekuensi: frekuensi,
        tenor: tenorBulan, jumlahCicilan: n, totalBunga: totalBunga, total: total,
        angsuran: rincian[0].jumlah, rincian: rincian
    };
}

function labelPeriode(frekuensi) {
    return frekuensi === 'mingguan' ? 'Minggu' : 'Bulan';
}
