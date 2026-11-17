/**
 * promptBuilder.js
 * Constructs secure system prompts and provides local analytical fallback engine.
 *
 * INTENT PRIORITY ORDER (strict, top-down):
 *  0. Access control guard (anggota role)
 *  1. Anggota personal analysis
 *  2. Member count / demographics
 *  3. Member payment status summary (lunas vs menunggak) -- NEW
 *  4. Recent transactions (top-5) -- NEW
 *  5. FULL financial summary (pemasukan+pengeluaran+saldo) -- BEFORE individual checks
 *  6. Period-specific queries
 *  7. Specific member queries
 *  8. Void transaction queries
 *  9. Specific date queries
 * 10. Category queries
 * 11. Who has paid (this month)
 * 12. Arrears / tunggakan (current period)
 * 13. All-time payment total
 * 14. Cash balance only
 * 15. Income only
 * 16. Expense only
 * 17. Users/accounts
 * 18. Audit log
 * 19. Transactions / history general
 * 20. Cash condition / surplus / deficit
 * 21. General summary (only 'ringkasan'/'laporan' -- NOT 'kas' alone)
 * 22. Fallback
 */

function formatRupiah(val) {
  const num = Number(val) || 0;
  return 'Rp ' + num.toLocaleString('id-ID');
}

function buildSystemPrompt(context, role, userInfo, compact) {
  userInfo = userInfo || {};
  compact = !!compact;
  const jsonContext = JSON.stringify(context || {}, null, compact ? 0 : 2);
  let prompt = 'Anda adalah asisten AI untuk Sistem Kas Berbasis Web.\n\n';
  prompt += 'PANDUAN UTAMA (WAJIB DIPATUHI SECARA KETAT):\n';
  prompt += '1. IDENTITAS DAN TUGAS AI:\n';
  prompt += '   - Jika pengguna bertanya identitas Anda, jawab: "Saya adalah asisten AI Sistem Kas yang membantu Anda memahami informasi anggota dan keuangan berdasarkan hak akses akun Anda."\n';
  prompt += '   - Jika pengguna bertanya tugas Anda, jawab: "Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda."\n\n';
  prompt += '2. JAWABAN SINGKAT, INFORMATIF, NATURAL:\n';
  prompt += '   - Utamakan satu kalimat untuk pertanyaan sederhana. Jangan bertele-tele.\n';
  prompt += '   - Jangan menambahkan basa-basi atau tawaran bantuan yang tidak diminta.\n\n';
  prompt += '3. ANTI-HALUSINASI:\n';
  prompt += '   - Jawab HANYA menggunakan data aktual dalam JSON di bawah.\n';
  prompt += '   - DILARANG mengarang angka atau data. Sistem READ-ONLY.\n\n';
  prompt += 'PENGGUNA LOGIN: ' + (userInfo.username || '-') + ' | Role: ' + role + '\n\n';
  if (role === 'anggota') {
    prompt += 'AKSES: Anggota biasa. Dilarang membocorkan data anggota lain.\n';
  } else if (role === 'bendahara') {
    prompt += 'AKSES: Bendahara. Boleh akses ringkasan kas, tunggakan, detail transaksi, pembayaran, dan statistik anggota.\n';
    prompt += 'BATASAN BENDAHARA: TIDAK boleh memberikan info tentang: (1) Akun/daftar pengguna sistem, (2) Rincian role user, (3) Log aktivitas/audit. Jika ditanya, arahkan ke Administrator.\n';
  } else {
    prompt += 'AKSES: Super User (Admin). Boleh akses semua data termasuk akun pengguna dan audit log.\n';
  }
  prompt += '\n=== DATA DATABASE ===\n' + jsonContext + '\n';
  return prompt;
}

function generateLocalAnalysis(userQuery, context, role, userInfo) {
  userInfo = userInfo || {};
  const q = (userQuery || '').toLowerCase();

  // INJECTION & PRIVILEGE ESCALATION GUARD
  var isInjectionAttempt = (
    q.includes('ignore previous') ||
    q.includes('abaikan instruksi') ||
    q.includes('sudo') ||
    q.includes('privilege') ||
    q.includes('debug mode') ||
    q.includes('override') ||
    q.includes('bypass')
  );
  if (isInjectionAttempt) {
    return 'Permintaan tidak dapat diproses. Sistem Kas beroperasi dalam lingkungan aman dan seluruh akses data dibatasi secara ketat berdasarkan peran akun Anda.';
  }

  // KEBIJAKAN HAK AKSES & BOUNDARY PRIVASI
  var isAccessPolicy = (
    q.includes('hak akses') || q.includes('kebijakan akses') || q.includes('batasan akses') || q.includes('izin akses') ||
    ((q.includes('tidak boleh') || q.includes('bukan hak') || q.includes('dilarang')) && (q.includes('akses') || q.includes('lihat') || q.includes('minta') || q.includes('tanya'))) ||
    (q.includes('apa yang terjadi') && (q.includes('akses') || q.includes('informasi anggota')))
  );
  if (isAccessPolicy) {
    return 'Sistem Kas menerapkan pembatasan hak akses berbasis peran (Role-Based Access Control). Jika Anda menanyakan informasi atau data anggota yang berada di luar wewenang akun Anda (misalnya data pribadi atau status pembayaran anggota lain oleh akun Anggota), sistem akan secara otomatis membatasi akses, menolak permintaan tersebut, dan hanya menyajikan informasi yang diizinkan untuk peran akun Anda.';
  }

  // ACCESS CONTROL GUARD
  if (role === 'anggota') {
    var aboutOthers = (
      q.includes('anggota lain') ||
      (q.includes('siapa') && (q.includes('menunggak') || q.includes('nunggak') || q.includes('tunggak') || q.includes('anggota'))) ||
      (q.includes('daftar') && (q.includes('anggota') || q.includes('menunggak') || q.includes('tunggak') || q.includes('pembayar'))) ||
      (q.includes('siapa saja') && (q.includes('anggota') || q.includes('bayar') || q.includes('menunggak'))) ||
      (q.includes('siapa yang') && q.includes('bayar'))
    );
    if (aboutOthers) return 'Maaf, Anda hanya dapat mengakses data pembayaran kas milik Anda sendiri.';
    if (q.includes('berapa anggota') || q.includes('ada berapa anggota') || q.includes('jumlah anggota') || q.includes('total anggota') || (q.includes('anggota') && (q.includes('jumlah') || q.includes('total') || q.includes('seluruh') || q.includes('berapa')))) {
      return 'Maaf, informasi jumlah anggota tidak dapat diakses oleh akun Anggota demi menjaga privasi data.';
    }
    if (q.includes('pengeluaran') || q.includes('uang keluar') || q.includes('biaya') || q.includes('pencatat') || q.includes('siapa yang mencatat')) {
      return 'Rincian pengeluaran kas dan pencatat transaksi dikelola langsung oleh Bendahara dan Administrator.';
    }
  }

  // ANGGOTA PERSONAL
  if (role === 'anggota') {
    var status = context.status_pembayaran_saya || {};
    var kasOrg = context.info_kas_organisasi || {};
    if (q.includes('transaksi') || q.includes('riwayat')) return 'Informasi riwayat transaksi umum dibatasi dan dikelola oleh Bendahara dan Administrator.';
    if (q.includes('status') || q.includes('tunggak') || q.includes('nunggak') || q.includes('tagihan') || q.includes('iuran')) {
      var isLunas = status.status_bulan_ini === 'lunas';
      var estimasi = status.estimasi_tunggakan != null ? status.estimasi_tunggakan : 0;
      var mingguBlm = status.minggu_belum_bayar != null ? status.minggu_belum_bayar : 0;
      if (isLunas) return 'Status pembayaran kas Anda bulan ini sudah lunas.';
      return 'Anda memiliki tunggakan kas sebanyak ' + mingguBlm + ' minggu dengan estimasi sebesar ' + formatRupiah(estimasi) + '.';
    }
    if (q.includes('saldo') || q.includes('sisa kas') || q.includes('total kas') || q.includes('kas organisasi')) {
      return 'Saldo kas organisasi saat ini sebesar ' + formatRupiah(kasOrg.saldo_kas_saat_ini != null ? kasOrg.saldo_kas_saat_ini : 0) + '.';
    }
    if (q.includes('bayar') || q.includes('pembayaran') || q.includes('setoran') || q.includes('pemasukan')) {
      return 'Total pembayaran kas Anda bulan ini sebesar ' + formatRupiah(status.total_terbayar_bulan_ini != null ? status.total_terbayar_bulan_ini : 0) + '.';
    }
    if (q.includes('pengeluaran') || q.includes('uang keluar') || q.includes('biaya')) return 'Rincian pengeluaran kas dikelola langsung oleh Bendahara dan Administrator.';
    if (q.includes('kondisi') || q.includes('surplus') || q.includes('defisit') || q.includes('analisis')) {
      var st = (status.status_bulan_ini === 'lunas') ? 'lunas' : 'menunggak';
      return 'Status pembayaran kas Anda bulan ini adalah ' + st + ' dengan estimasi tunggakan ' + formatRupiah(status.estimasi_tunggakan != null ? status.estimasi_tunggakan : 0) + '.';
    }
  }

  // SUPER USER & BENDAHARA
  var kas = context.ringkasan_kas || {};
  var tunggakan = context.ringkasan_tunggakan || {};
  var pembayaran = context.ringkasan_pembayaran_anggota || {};
  var transaksi = context.ringkasan_transaksi || {};
  var infoAnggota = context.informasi_anggota || {};
  var sistem = context.ringkasan_sistem || {};
  var target = context.query_tertarget || {};
  var trxTerbaru = context.transaksi_pembayaran_terbaru || [];
  var periodeLaporan = context.periode_laporan || 'bulan ini';

  // 0a. PERBANDINGAN PENGELUARAN BULAN INI VS BULAN LALU
  var isExpenseComparison = (
    (q.includes('banding') || q.includes('perbandingan')) &&
    (q.includes('pengeluaran') || q.includes('biaya'))
  );
  if (isExpenseComparison) {
    var pBulanIni = formatRupiah(kas.pengeluaran_bulan_ini || 0);
    var pBulanLalu = formatRupiah(kas.pengeluaran_bulan_lalu || 0);
    var selisih = Number((kas.pengeluaran_bulan_ini || 0) - (kas.pengeluaran_bulan_lalu || 0));
    var selisihStr = formatRupiah(Math.abs(selisih));
    var bulanIniLabel = periodeLaporan;
    var bulanLaluLabel = kas.nama_bulan_lalu || 'bulan lalu';
    var statusBanding = selisih > 0 ? ('kenaikan pengeluaran sebesar ' + selisihStr) : (selisih < 0 ? ('penurunan pengeluaran sebesar ' + selisihStr) : 'nominal pengeluaran yang sama');
    return 'Pengeluaran kas bulan ini (' + bulanIniLabel + ') sebesar ' + pBulanIni + ', sedangkan pada bulan lalu (' + bulanLaluLabel + ') tercatat ' + pBulanLalu + ' (terdapat ' + statusBanding + ').';
  }

  // 0b. RINCIAN SALDO KAS
  if (q.includes('rincian saldo') || q.includes('detail saldo') || q.includes('penjelasan saldo')) {
    var saldo = formatRupiah(kas.saldo_kas_saat_ini || 0);
    var pemasukan = formatRupiah(kas.total_pemasukan_semua || 0);
    var pengeluaran = formatRupiah(kas.total_pengeluaran_semua || 0);
    return 'Rincian saldo kas saat ini adalah ' + saldo + ', yang bersumber dari akumulasi total pemasukan kas sebesar ' + pemasukan + ' dikurangi total pengeluaran kas sebesar ' + pengeluaran + '.';
  }

  // 0c. RENTANG TANGGAL (DATE RANGE)
  if (target.rentang_tanggal_ditemukan) {
    var rawStart = target.rentang_tanggal_ditemukan.raw_start;
    var rawEnd = target.rentang_tanggal_ditemukan.raw_end;
    var isPengeluaranRange = (q.includes('pengeluaran') || q.includes('keluar'));
    var isPemasukanRange = (q.includes('pemasukan') || q.includes('masuk'));

    if (isPengeluaranRange && !isPemasukanRange) {
      var dftKeluar = context.daftar_transaksi_pengeluaran || [];
      var totR = 0;
      dftKeluar.forEach(function(t){ totR += Number(t.jumlah) || 0; });
      return 'Total pengeluaran kas pada rentang waktu ' + rawStart + ' sampai ' + rawEnd + ' adalah sebesar ' + formatRupiah(totR) + ' (tercatat ' + dftKeluar.length + ' transaksi).';
    } else if (isPemasukanRange && !isPengeluaranRange) {
      var totM = Number(kas.total_pemasukan_semua || 0);
      return 'Total pemasukan kas pada rentang waktu ' + rawStart + ' sampai ' + rawEnd + ' adalah sebesar ' + formatRupiah(totM) + '.';
    }
  }

  // 0d. DETAIL, TUJUAN, PENCATAT, KANDIDAT, & MULTI-TABEL PENGELUARAN
  var isSpecificExpense = (
    Boolean(target.pertanyaan_tujuan_pengeluaran) ||
    Boolean(target.pertanyaan_pencatat_pengeluaran) ||
    Boolean(target.transaksi_pengeluaran_terpilih) ||
    Boolean(target.kandidat_pengeluaran) ||
    Boolean(target.transaksi_pengeluaran_terbesar) ||
    Boolean(target.pengeluaran_nominal_tidak_ditemukan) ||
    (q.includes('terbesar') && (q.includes('pengeluaran') || q.includes('keluar') || q.includes('transaksi'))) ||
    q.includes('buat apa') || q.includes('untuk apa') ||
    q.includes('tujuan pengeluaran') || q.includes('keperluan apa') ||
    q.includes('siapa yang mencatat') || q.includes('dicatat oleh siapa') || q.includes('siapa pencatat') ||
    (q.includes('pengeluaran') && (q.includes('detail') || q.includes('rincian') || q.includes('keterangan') || q.includes('itu') || q.includes('tadi') || q.includes('kemarin'))) ||
    (q.includes('uang') && q.includes('keluar') && (q.includes('apa') || q.includes('siapa')))
  );

  if (isSpecificExpense) {
    if (target.pengeluaran_nominal_tidak_ditemukan) {
      return 'Berdasarkan penelusuran data transaksi database Sistem Kas, tidak ditemukan transaksi pengeluaran kas senilai ' + formatRupiah(target.pengeluaran_nominal_tidak_ditemukan) + '.';
    }

    if (target.kandidat_pengeluaran && target.kandidat_pengeluaran.length > 1) {
      var kandidat = target.kandidat_pengeluaran;
      var nomStr = formatRupiah(kandidat[0].jumlah || 0);
      var lines = ['Ditemukan ' + kandidat.length + ' kandidat transaksi pengeluaran senilai ' + nomStr + ':'];
      kandidat.forEach(function(kd, idx) {
        var ket = (kd.keterangan && kd.keterangan !== '-') ? kd.keterangan : (kd.nama_kategori + ' (' + (kd.deskripsi_kategori || '') + ')');
        var pencatat = kd.creator_nama || kd.creator_username || 'Petugas';
        lines.push((idx + 1) + '. Kode ' + kd.kode_transaksi + ' (' + kd.tanggal_transaksi + ') — ' + ket + ', dicatat oleh ' + pencatat);
      });
      lines.push('Silakan sebutkan kode transaksi atau tanggal yang Anda maksud untuk melihat rincian lengkapnya.');
      return lines.join('\n');
    }

    if (target.transaksi_pengeluaran_terbesar || ((q.includes('terbesar') || q.includes('paling banyak')) && (q.includes('pengeluaran') || q.includes('keluar')))) {
      var tMax = target.transaksi_pengeluaran_terbesar || context.pengeluaran_terbesar;
      if (tMax) {
        var nomMax = formatRupiah(tMax.jumlah || 0);
        var katMax = tMax.nama_kategori || 'Pengeluaran Umum';
        var deskMax = tMax.deskripsi_kategori ? (' (' + tMax.deskripsi_kategori + ')') : '';
        var pencatatMax = tMax.creator_nama || tMax.creator_username || 'Petugas';
        var ketMaxDb = (tMax.keterangan || '').trim();
        var ketMaxInfo = (ketMaxDb === '' || ketMaxDb === '-')
          ? "Pada database, kolom keterangan tidak diisi rincian khusus (tercatat '-')."
          : ('Keterangan catatan: "' + ketMaxDb + '".');
        return 'Pengeluaran kas terbesar yang tercatat adalah sebesar ' + nomMax + ' (kode: ' + tMax.kode_transaksi + ' pada tanggal ' + tMax.tanggal_transaksi + ') untuk kategori ' + katMax + deskMax + ', dicatat oleh ' + pencatatMax + '. ' + ketMaxInfo;
      } else {
        return 'Belum ada transaksi pengeluaran yang tercatat pada database.';
      }
    }

    var tExp = target.transaksi_pengeluaran_terpilih;
    if (!tExp && context.daftar_transaksi_pengeluaran && context.daftar_transaksi_pengeluaran.length > 0) {
      tExp = context.daftar_transaksi_pengeluaran[0];
    }

    if (tExp) {
      var nomExp = formatRupiah(tExp.jumlah || 0);
      var katExp = tExp.nama_kategori || 'Pengeluaran Umum';
      var deskExp = tExp.deskripsi_kategori ? (' (' + tExp.deskripsi_kategori + ')') : '';
      var pencatatExp = tExp.creator_nama || tExp.creator_username || 'Petugas';
      var ketExpDb = (tExp.keterangan || '').trim();

      if ((q.includes('siapa yang mencatat') || q.includes('dicatat oleh siapa') || q.includes('siapa pencatat') || q.includes('mencatatnya')) && !q.includes('buat apa') && !q.includes('untuk apa')) {
        return 'Transaksi pengeluaran kas sebesar ' + nomExp + ' (kode: ' + tExp.kode_transaksi + ' pada tanggal ' + tExp.tanggal_transaksi + ') dicatat oleh ' + pencatatExp + '.';
      }

      if (q.includes('kapan') && (q.includes('transaksi') || q.includes('pengeluaran') || q.includes('dilakukan'))) {
        return 'Transaksi pengeluaran kas sebesar ' + nomExp + ' (kode: ' + tExp.kode_transaksi + ') dilakukan pada tanggal ' + tExp.tanggal_transaksi + ' dan dicatat oleh ' + pencatatExp + '.';
      }

      var ketExpInfo = (ketExpDb === '' || ketExpDb === '-')
        ? "Pada catatan transaksi, kolom keterangan tidak diisi secara spesifik (tercatat '-')."
        : ('Keterangan catatan: "' + ketExpDb + '".');

      return 'Pengeluaran kas sebesar ' + nomExp + ' (kode: ' + tExp.kode_transaksi + ' tanggal ' + tExp.tanggal_transaksi + ') tercatat pada kategori ' + katExp + deskExp + ' dan dicatat oleh ' + pencatatExp + '. ' + ketExpInfo;
    }

    return 'Berdasarkan data database Sistem Kas, belum ada catatan transaksi pengeluaran yang sesuai.';
  }

  // 1. STATUS PEMBAYARAN ANGGOTA (lunas vs menunggak)
  var isMemberPayStatus = (
    (q.includes('lunas') && (q.includes('menunggak') || q.includes('tunggak') || q.includes('nunggak') || q.includes('belum'))) ||
    (q.includes('berapa') && q.includes('anggota') && (q.includes('lunas') || q.includes('menunggak') || q.includes('nunggak') || q.includes('belum bayar'))) ||
    (q.includes('sudah lunas') && q.includes('anggota')) ||
    (q.includes('anggota') && q.includes('belum membayar'))
  );
  if (isMemberPayStatus) {
    if (!context.ringkasan_tunggakan || typeof context.ringkasan_tunggakan !== 'object') {
      return 'Mohon maaf, data status pembayaran anggota belum tersedia dalam sistem kas.';
    }
    var jmlLunas = tunggakan.jumlah_anggota_lunas != null ? tunggakan.jumlah_anggota_lunas : 0;
    var jmlMenunggak = tunggakan.jumlah_anggota_menunggak != null ? tunggakan.jumlah_anggota_menunggak : 0;
    var totalAng = infoAnggota.total_anggota != null ? infoAnggota.total_anggota : (jmlLunas + jmlMenunggak);
    if (jmlMenunggak === 0) return 'Pada ' + periodeLaporan + ', seluruh ' + jmlLunas + ' anggota telah lunas dan tidak ada yang menunggak.';
    var dftArr = tunggakan.daftar_anggota_menunggak || [];
    var nmArr = dftArr.slice(0, 3).map(function(m){return m.nama;});
    var nmStr = nmArr.join(', ');
    if (dftArr.length > 3) nmStr += ', dan ' + (dftArr.length - 3) + ' lainnya';
    return 'Pada ' + periodeLaporan + ', ' + jmlLunas + ' dari ' + totalAng + ' anggota sudah lunas dan ' + jmlMenunggak + ' masih menunggak' + (nmStr ? ' (' + nmStr + ')' : '') + '.';
  }

  // 1b. JUMLAH ANGGOTA & DEMOGRAFI
  var isExplicitPayment = (
    (q.includes('pembayaran') && (q.includes('total') || q.includes('seluruh') || q.includes('berapa'))) ||
    (q.includes('setoran') && (q.includes('total') || q.includes('berapa'))) ||
    (q.includes('anggota') && (q.includes('membayar') || q.includes('bayar')) && (q.includes('berapa') || q.includes('jumlah')))
  );
  var isMemberCount = (
    !isExplicitPayment &&
    !q.includes('lunas') && !q.includes('menunggak') && !q.includes('nunggak') && !q.includes('tunggak') && !q.includes('belum bayar') &&
    (
      q.includes('berapa anggota') || q.includes('ada berapa anggota') ||
      q.includes('jumlah anggota') || q.includes('total anggota') ||
      q.includes('anggota terdaftar') ||
      (q.includes('anggota') && (q.includes('jumlah') || q.includes('total') || q.includes('berapa'))) ||
      (q.includes('anggota') && q.includes('aktif') && !q.includes('bayar')) ||
      (q.includes('siapa saja') && q.includes('anggota') && !q.includes('bayar'))
    )
  );
  if (isMemberCount) {
    if (!context.informasi_anggota || typeof context.informasi_anggota !== 'object') {
      return 'Mohon maaf, data anggota belum tersedia dalam sistem kas.';
    }
    var total = infoAnggota.total_anggota != null ? infoAnggota.total_anggota : 0;
    var aktif = infoAnggota.total_anggota_aktif != null ? infoAnggota.total_anggota_aktif : total;
    var nonAktif = infoAnggota.total_anggota_nonaktif != null ? infoAnggota.total_anggota_nonaktif : (total - aktif);
    if (q.includes('siapa saja')) return 'Total anggota terdaftar adalah ' + total + ' orang; daftar lengkap dapat dilihat pada menu Data Anggota.';
    if (q.includes('seluruh') && q.includes('aktif')) {
      var kelasList = infoAnggota.daftar_kelas && Object.keys(infoAnggota.daftar_kelas).length > 0 ? Object.keys(infoAnggota.daftar_kelas).join(', ') : '';
      return 'Total anggota terdaftar adalah ' + total + ' orang dan seluruhnya berstatus aktif' + (kelasList ? ' di kelas ' + kelasList : '') + '.';
    }
    if (nonAktif > 0) return 'Total anggota terdaftar adalah ' + total + ' orang (' + aktif + ' aktif dan ' + nonAktif + ' tidak aktif).';
    return 'Total anggota terdaftar adalah ' + total + ' orang.';
  }

  if (q.includes('laki-laki') || q.includes('perempuan') || q.includes('gender')) {
    var gender = infoAnggota.rincian_gender || {};
    return 'Terdapat ' + (gender.L != null ? gender.L : 0) + ' anggota laki-laki dan ' + (gender.P != null ? gender.P : 0) + ' anggota perempuan.';
  }
  if (q.includes('kelas') && q.includes('anggota')) {
    var kList = infoAnggota.daftar_kelas ? Object.entries(infoAnggota.daftar_kelas).map(function(e){return e[0]+' ('+e[1]+' orang)';}).join(', ') : 'tercatat dalam sistem';
    return 'Seluruh ' + (infoAnggota.total_anggota != null ? infoAnggota.total_anggota : 0) + ' anggota terdaftar berada di kelas ' + kList + '.';
  }

  // 3. TRANSAKSI PEMBAYARAN TERBARU (top-5)
  var isRecentTrx = (
    ((q.includes('terbaru') || q.includes('terakhir')) && (q.includes('transaksi') || q.includes('pembayaran'))) ||
    ((q.includes('lima') || q.includes('5')) && (q.includes('transaksi') || q.includes('pembayaran'))) ||
    (q.includes('tampilkan') && (q.includes('transaksi') || q.includes('pembayaran')))
  );
  if (isRecentTrx) {
    if (trxTerbaru.length === 0) {
      var sampel = context.sampel_transaksi_pemasukan || [];
      if (sampel.length === 0) return 'Belum ada transaksi pembayaran yang tercatat.';
      var lns = sampel.slice(0, 5).map(function(t, i){ return (i+1)+'. '+(t.tanggal||'-')+' - '+formatRupiah(t.jumlah)+' ('+(t.keterangan||t.kategori||'-')+')'; });
      return 'Lima transaksi pemasukan terbaru:\n' + lns.join('\n');
    }
    var lim = Math.min(5, trxTerbaru.length);
    var lns2 = [];
    for (var i = 0; i < lim; i++) {
      var t = trxTerbaru[i];
      lns2.push((i+1)+'. '+(t.nama_anggota||t.nama||'Anggota')+' - '+formatRupiah(t.nominal)+' ('+(t.tanggal_bayar||'-')+')');
    }
    return 'Lima transaksi pembayaran terbaru:\n' + lns2.join('\n');
  }

  // 4. RINGKASAN KEUANGAN LENGKAP (SEBELUM individual pemasukan/pengeluaran)
  var isFullFinSum = (
    (q.includes('pemasukan') && q.includes('pengeluaran') && (q.includes('saldo') || q.includes('kas'))) ||
    (q.includes('total pemasukan') && q.includes('total pengeluaran')) ||
    (q.includes('pemasukan') && q.includes('pengeluaran') && q.includes('total')) ||
    (q.includes('arus kas') && q.includes('saldo'))
  );
  if (isFullFinSum) {
    if (!context.ringkasan_kas || typeof context.ringkasan_kas !== 'object') {
      return 'Mohon maaf, data keuangan kas belum tersedia dalam sistem kas.';
    }
    var saldo = kas.saldo_kas_saat_ini != null ? kas.saldo_kas_saat_ini : 0;
    var masuk = kas.total_pemasukan_semua != null ? kas.total_pemasukan_semua : 0;
    var keluar = kas.total_pengeluaran_semua != null ? kas.total_pengeluaran_semua : 0;
    return 'Total pemasukan kas: ' + formatRupiah(masuk) + ', total pengeluaran: ' + formatRupiah(keluar) + ', saldo kas saat ini: ' + formatRupiah(saldo) + '.';
  }

  // 5a0. TUNGGAKAN MULTIANGGOTA (nama anggota + periode)
  var isArrearsQuery = (q.includes('tunggak') || q.includes('nunggak') || q.includes('belum'));
  if (isArrearsQuery && target.periode && target.anggota_terpilih_list && target.anggota_terpilih_list.length > 0) {
    var pT = target.periode;
    var nbTgt = pT.nama_bulan;
    var thTgt = pT.tahun;
    var menunggakMap = {};
    var dftMngg = pT.daftar_menunggak || [];
    for (var i = 0; i < dftMngg.length; i++) {
      menunggakMap[String(dftMngg[i].nama || '').toLowerCase()] = dftMngg[i];
    }
    var lines = [];
    for (var j = 0; j < target.anggota_terpilih_list.length; j++) {
      var ag = target.anggota_terpilih_list[j];
      var namaAg = ag.profil.nama;
      var mng = menunggakMap[String(namaAg || '').toLowerCase()] || null;
      var est = mng ? Number(mng.estimasi_tunggakan || 0) : 0;
      var minggu = mng ? Number(mng.minggu_belum_bayar || 0) : 0;
      var statusStr = mng ? ('menunggak ' + minggu + ' minggu') : 'lunas';
      lines.push(namaAg + ': ' + formatRupiah(est) + ' (' + statusStr + ')');
    }
    return 'Nominal tunggakan pada periode ' + nbTgt + ' ' + thTgt + ': ' + lines.join('; ') + '.';
  }

  // 5a. KOMBINASI ANGGOTA SPESIFIK + PERIODE SPESIFIK
  // Prioritaskan anggota jika nama anggota dan bulan disebut bersamaan.
  var isPaymentQuery = (q.includes('bayar') || q.includes('pembayaran') || q.includes('setoran') || q.includes('pemasukan'));
  if (isPaymentQuery && !isArrearsQuery && target.anggota_terpilih && target.periode) {
    var ag = target.anggota_terpilih;
    var namaAg = ag.profil.nama;
    var pInfo = ag.pembayaran || {};
    var pT = target.periode;
    var nbTgt = pT.nama_bulan;
    var thTgt = pT.tahun;
    var matchedCount = 0;
    var matchedTotal = 0;
    var rincian = pInfo.rincian || [];
    for (var i = 0; i < rincian.length; i++) {
      if (String(rincian[i].periode || '').trim() === (nbTgt + ' ' + thTgt).trim()) {
        matchedCount++;
        matchedTotal += Number(rincian[i].nominal) || 0;
      }
    }
    if (matchedCount === 0) {
      return namaAg + ' tidak memiliki catatan pembayaran pada periode ' + nbTgt + ' ' + thTgt + '.';
    }
    return namaAg + ' melakukan ' + matchedCount + ' pembayaran pada periode ' + nbTgt + ' ' + thTgt + ' dengan total nominal ' + formatRupiah(matchedTotal) + '.';
  }

  // 5. QUERY PERIODE SPESIFIK
  if (target.periode) {
    var pT = target.periode;
    var nbT = pT.nama_bulan; var tahT = pT.tahun;
    var nomT = formatRupiah(pT.total_nominal); var jmlT = pT.jumlah_transaksi; var jmlTunT = pT.jumlah_menunggak;
    if (q.includes('tunggak') || q.includes('nunggak') || (q.includes('siapa') && (q.includes('belum') || q.includes('tunggak')))) {
      if (jmlTunT === 0) return 'Tidak ada anggota yang menunggak untuk periode ' + nbT + ' ' + tahT + '.';
      var lT = (pT.daftar_menunggak||[]).map(function(m){return m.nama+' ('+formatRupiah(m.estimasi_tunggakan)+')';});
      return 'Terdapat ' + jmlTunT + ' anggota yang menunggak pada periode ' + nbT + ' ' + tahT + ': ' + lT.join(', ') + '.';
    }
    if (q.includes('siapa') && (q.includes('bayar') || q.includes('lunas'))) {
      var pL = pT.daftar_pembayar || [];
      if (pL.length === 0) return 'Belum ada catatan pembayaran pada periode ' + nbT + ' ' + tahT + '.';
      var nms = pL.map(function(pl){return pl.nama+' ('+formatRupiah(pl.nominal)+')';});
      return 'Pada periode ' + nbT + ' ' + tahT + ', ' + nms.length + ' anggota telah membayar: ' + nms.join(', ') + '.';
    }
    var isPayingCount = (
      (q.includes('jumlah') || q.includes('berapa') || q.includes('banyak')) &&
      (q.includes('anggota') || q.includes('orang')) &&
      (q.includes('membayar') || q.includes('bayar'))
    );
    var isPayAmount = (
      q.includes('total') || q.includes('nominal') || q.includes('uang') ||
      q.includes('biaya') || q.includes('dana') || q.includes('yang dibayarkan') ||
      q.includes('total pembayaran') || q.includes('total uang')
    );

    if (isPayingCount && isPayAmount) {
      var jmlAngBayarGab = pT.jumlah_anggota_bayar != null ? pT.jumlah_anggota_bayar : (pT.daftar_pembayar ? pT.daftar_pembayar.length : 0);
      var totAngGab = infoAnggota.total_anggota != null ? infoAnggota.total_anggota : 8;
      return 'Pada periode ' + nbT + ' ' + tahT + ', terdapat ' + jmlAngBayarGab + ' dari ' + totAngGab + ' anggota yang telah membayar kas dengan total nominal uang yang dibayarkan sebesar ' + nomT + ' (dari ' + jmlT + ' transaksi pembayaran).';
    }
    if (isPayingCount) {
      var jmlAngBayar = pT.jumlah_anggota_bayar != null ? pT.jumlah_anggota_bayar : (pT.daftar_pembayar ? pT.daftar_pembayar.length : 0);
      var totAng3 = infoAnggota.total_anggota != null ? infoAnggota.total_anggota : 8;
      return 'Pada periode ' + nbT + ' ' + tahT + ', terdapat ' + jmlAngBayar + ' dari ' + totAng3 + ' anggota yang telah membayar kas.';
    }
    if (q.includes('lunas') || (q.includes('berapa') && (q.includes('lunas') || q.includes('menunggak')))) {
      var totAng2 = infoAnggota.total_anggota != null ? infoAnggota.total_anggota : 0;
      var jmlL2 = totAng2 - jmlTunT;
      if (jmlTunT === 0) return 'Pada periode ' + nbT + ' ' + tahT + ', seluruh ' + jmlL2 + ' anggota telah lunas dan tidak ada yang menunggak.';
      return 'Pada periode ' + nbT + ' ' + tahT + ', ' + jmlL2 + ' anggota sudah lunas dan ' + jmlTunT + ' masih menunggak.';
    }
    if (q.includes('bayar') || q.includes('pembayaran') || q.includes('total') || q.includes('berapa')) {
      return 'Total pembayaran kas pada periode ' + nbT + ' ' + tahT + ' adalah sebesar ' + nomT + ' dari ' + jmlT + ' transaksi pembayaran.';
    }
  }

  // 6. QUERY ANGGOTA SPESIFIK
  if (target.anggota_terpilih) {
    var ag = target.anggota_terpilih;
    var namaAg = ag.profil.nama;
    var pInfo = ag.pembayaran;
    var rArr = (pInfo.rincian||[]).map(function(r){return r.periode+': '+formatRupiah(r.nominal);});
    var rStr = rArr.length > 0 ? ' (' + rArr.join(', ') + ')' : '';
    return namaAg + ' telah membayar total ' + formatRupiah(pInfo.total_nominal) + ' dari ' + pInfo.jumlah_transaksi + ' transaksi' + rStr + '.';
  }

  // 7. VOID
  if (q.includes('void') || q.includes('batal') || q.includes('dibatalkan')) {
    if (!context.ringkasan_transaksi || typeof context.ringkasan_transaksi !== 'object') {
      return 'Mohon maaf, data status transaksi belum tersedia dalam sistem kas.';
    }
    return 'Terdapat ' + (transaksi.total_transaksi_aktif||0) + ' transaksi kas aktif senilai ' + formatRupiah(kas.total_pemasukan_semua||0) + ' dan ' + (transaksi.total_transaksi_void||0) + ' transaksi void (dibatalkan) senilai ' + formatRupiah(transaksi.total_nominal_void||0) + '.';
  }

  // 8. TANGGAL SPESIFIK
  if (target.tanggal_transaksi) {
    var tgl = target.tanggal_transaksi.tanggal;
    var rTgl = target.tanggal_transaksi.rincian || [];
    var totTgl = 0; var cntTgl = 0;
    rTgl.forEach(function(rt){ totTgl += Number(rt.total)||0; cntTgl += Number(rt.jml)||0; });
    return 'Pada tanggal ' + tgl + ' terdapat ' + cntTgl + ' transaksi pemasukan aktif dengan total ' + formatRupiah(totTgl) + '.';
  }

  // 9. KATEGORI
  if (q.includes('kategori')) {
    if (!transaksi.kategori_transaksi || !Array.isArray(transaksi.kategori_transaksi)) {
      return 'Mohon maaf, data kategori transaksi belum tersedia dalam sistem kas.';
    }
    var kl = transaksi.kategori_transaksi || [];
    var kn = kl.map(function(k){return k.nama_kategori;}).join(', ');
    return 'Terdapat ' + kl.length + ' kategori transaksi (' + kn + ') dengan total transaksi aktif sebesar ' + formatRupiah(kas.total_pemasukan_semua||0) + '.';
  }

  // 10. SIAPA YANG SUDAH BAYAR BULAN INI
  var isWhoPaid = (
    (q.includes('siapa') && q.includes('bayar') && !q.includes('belum') && !q.includes('tunggak') && !q.includes('nunggak')) ||
    q.includes('yang sudah bayar') || q.includes('yang telah bayar') || q.includes('daftar pembayar')
  );
  if (isWhoPaid) {
    if (!context.ringkasan_pembayaran_anggota || typeof context.ringkasan_pembayaran_anggota !== 'object') {
      return 'Mohon maaf, data pembayaran anggota belum tersedia dalam sistem kas.';
    }
    var pList = pembayaran.daftar_pembayaran_bulan_ini || [];
    if (pList.length === 0) return 'Belum ada transaksi pembayaran untuk bulan ini.';
    var pNms = pList.map(function(pb){return pb.nama+' ('+formatRupiah(pb.nominal)+')';});
    return 'Pada ' + periodeLaporan + ', ' + pList.length + ' anggota telah membayar dengan total ' + formatRupiah(pembayaran.total_pembayaran_bulan_ini||0) + ': ' + pNms.join(', ') + '.';
  }

  // 11. TUNGGAKAN / MENUNGGAK
  var isTunggakan = (
    q.includes('belum membayar') || q.includes('tunggakan') || q.includes('menunggak') || q.includes('nunggak') ||
    (q.includes('siapa') && (q.includes('belum') || q.includes('tunggak') || q.includes('menunggak')))
  );
  if (isTunggakan) {
    if (!context.ringkasan_tunggakan || typeof context.ringkasan_tunggakan !== 'object') {
      return 'Mohon maaf, data tunggakan kas belum tersedia dalam sistem kas.';
    }
    var totEst = tunggakan.total_estimasi_tunggakan != null ? tunggakan.total_estimasi_tunggakan : 0;
    var jmlMngg = tunggakan.jumlah_anggota_menunggak != null ? tunggakan.jumlah_anggota_menunggak : 0;
    var dftMngg = tunggakan.daftar_anggota_menunggak || [];
    if (jmlMngg === 0 || dftMngg.length === 0) return 'Tidak ada anggota yang menunggak untuk ' + periodeLaporan + '.';
    var lim2 = Math.min(5, dftMngg.length);
    var mnggNames = [];
    for (var j = 0; j < lim2; j++) { mnggNames.push(dftMngg[j].nama + ' (' + formatRupiah(dftMngg[j].estimasi_tunggakan) + ')'); }
    var listStr = mnggNames.join(', ');
    if (dftMngg.length > lim2) listStr += ', dan ' + (dftMngg.length - lim2) + ' anggota lainnya';
    return 'Terdapat ' + jmlMngg + ' anggota yang menunggak dengan total estimasi ' + formatRupiah(totEst) + ': ' + listStr + '.';
  }

  // 11b. REKONSILIASI / PERBEDAAN SALDO KAS VS PEMBAYARAN IURAN
  var isReconciliation = (
    q.includes('saldo') && (q.includes('pembayaran') || q.includes('iuran')) &&
    (q.includes('beda') || q.includes('berbeda') || q.includes('perbedaan') || q.includes('selisih') || q.includes('mengapa') || q.includes('kenapa') || q.includes('sama') || q.includes('alasan') || q.includes('penyebab'))
  );
  if (isReconciliation) {
    var rek = context.rekonsiliasi_kas || {};
    var saldoNom = formatRupiah(kas.saldo_kas_saat_ini || 0);
    var pemNom = formatRupiah(pembayaran.total_semua_pembayaran_nominal || 0);
    var selisih = Number((kas.saldo_kas_saat_ini || 0) - (pembayaran.total_semua_pembayaran_nominal || 0));
    var selisihNom = formatRupiah(Math.abs(selisih));

    if (Math.abs(selisih) < 0.01) {
      return 'Saldo kas saat ini (' + saldoNom + ') sama dengan total pembayaran iuran anggota (' + pemNom + ') karena seluruh kas masuk berasal dari pembayaran iuran dan belum ada pengeluaran kas.';
    }

    var trxLangsung = rek.rincian_transaksi_langsung || [];
    var totLangsung = Number(rek.total_nominal_langsung || 0);
    if (trxLangsung.length > 0 && Math.abs(selisih - totLangsung) < 0.01) {
      return 'Saldo kas saat ini sebesar ' + saldoNom + ', sedangkan total pembayaran iuran anggota adalah ' + pemNom + ' (terdapat selisih ' + selisihNom + '). Berdasarkan data transaksi aktual, selisih tersebut disebabkan oleh adanya ' + trxLangsung.length + ' transaksi pemasukan kas langsung senilai ' + formatRupiah(totLangsung) + ' (' + trxLangsung.join(', ') + ') yang dicatat langsung pada buku transaksi kas tanpa melalui modul pembayaran iuran anggota.';
    }

    var pengeluaranTotal = Number(kas.total_pengeluaran_semua || 0);
    if (pengeluaranTotal > 0) {
      return 'Saldo kas saat ini sebesar ' + saldoNom + ' berbeda dari total pembayaran iuran (' + pemNom + ') karena terdapat pengeluaran kas sebesar ' + formatRupiah(pengeluaranTotal) + ' serta mutasi kas lain yang tercatat pada sistem.';
    }

    return 'Saldo kas saat ini adalah ' + saldoNom + ' dan total pembayaran iuran anggota yang tercatat adalah ' + pemNom + ' (selisih ' + selisihNom + '). Berdasarkan data sistem saat ini, belum cukup data transaksi untuk menyimpulkan secara pasti penyebab selisih tersebut.';
  }

  // 12. TOTAL PEMBAYARAN SELURUHNYA
  if ((q.includes('total pembayaran') || q.includes('seluruh pembayaran')) && !q.includes('tunggak')) {
    if (!context.ringkasan_pembayaran_anggota) {
      return 'Mohon maaf, data pembayaran kas belum tersedia dalam sistem kas.';
    }
    return 'Total seluruh pembayaran kas yang tercatat adalah sebesar ' + formatRupiah(pembayaran.total_semua_pembayaran_nominal||0) + ' dari ' + (pembayaran.total_semua_pembayaran_transaksi||0) + ' transaksi pembayaran.';
  }

  // 13. SALDO KAS
  if (q.includes('saldo') || q.includes('sisa kas') || q.includes('total kas')) {
    if (!context.ringkasan_kas || typeof context.ringkasan_kas !== 'object') {
      return 'Mohon maaf, data saldo kas belum tersedia dalam sistem kas.';
    }
    return 'Saldo kas saat ini sebesar ' + formatRupiah(kas.saldo_kas_saat_ini||0) + ', dengan total pemasukan ' + formatRupiah(kas.total_pemasukan_semua||0) + ' dan total pengeluaran ' + formatRupiah(kas.total_pengeluaran_semua||0) + '.';
  }

  // 14. PEMASUKAN
  if (q.includes('pemasukan') || q.includes('uang masuk') || q.includes('kas masuk') ||
      (q.includes('pembayaran') && !q.includes('tunggak') && !q.includes('menunggak') && !q.includes('nunggak') && !q.includes('belum'))) {
    if (!context.ringkasan_kas || typeof context.ringkasan_kas !== 'object') {
      return 'Mohon maaf, data pemasukan kas belum tersedia dalam sistem kas.';
    }
    return 'Pemasukan kas ' + periodeLaporan + ' sebesar ' + formatRupiah(kas.pemasukan_bulan_ini||0) + ', dengan akumulasi keseluruhan ' + formatRupiah(kas.total_pemasukan_semua||0) + '.';
  }

  // 15. PENGELUARAN
  var isExpense = (
    q.includes('pengeluaran') ||
    q.includes('uang keluar') ||
    (q.includes('biaya') && (q.includes('kas') || q.includes('operasional') || q.includes('total biaya'))) ||
    (q.includes('belanja') && q.includes('kas'))
  );
  if (isExpense) {
    if (!context.ringkasan_kas || typeof context.ringkasan_kas !== 'object') {
      return 'Mohon maaf, data pengeluaran kas belum tersedia dalam sistem kas.';
    }
    return 'Pengeluaran kas ' + periodeLaporan + ' sebesar ' + formatRupiah(kas.pengeluaran_bulan_ini||0) + ', dengan akumulasi keseluruhan ' + formatRupiah(kas.total_pengeluaran_semua||0) + '.';
  }

  // 16. USERS/AKUN (hanya super_user)
  var isUserCountQuery = (
    (
      q.includes('jumlah user') || q.includes('jumlah pengguna') || q.includes('jumlah akun') ||
      q.includes('total user') || q.includes('total akun') || q.includes('berapa user') ||
      q.includes('berapa akun') || q.includes('berapa pengguna') ||
      q.includes('akun terdaftar') || q.includes('pengguna terdaftar') || q.includes('daftar pengguna') ||
      (q.includes('user') && (q.includes('aktif') || q.includes('berapa')) && !q.includes('hak akses') && !q.includes('kebijakan')) ||
      (q.includes('akun') && (q.includes('aktif') || q.includes('role') || q.includes('terdaftar')) && !q.includes('hak akses') && !q.includes('kebijakan'))
    )
  );
  if (isUserCountQuery) {
    if (role === 'anggota') {
      return 'Informasi akun pengguna sistem tidak dapat diakses oleh akun Anggota demi menjaga privasi dan keamanan data.';
    }
    // Bendahara tidak punya akses ke data akun pengguna
    if (role === 'bendahara') {
      return 'Informasi akun pengguna sistem hanya dapat diakses oleh Administrator (Super User). Sebagai Bendahara, Anda dapat mengakses data keuangan, transaksi, pembayaran, dan laporan keuangan.';
    }
    if (!context.ringkasan_sistem || typeof context.ringkasan_sistem !== 'object') {
      return 'Mohon maaf, data akun pengguna belum tersedia dalam sistem kas.';
    }
    var totUsr = sistem.total_user != null ? sistem.total_user : 0;
    var rUser = sistem.rincian_role_user || {};
    var su = rUser.super_user != null ? rUser.super_user : 0;
    var bd = rUser.bendahara != null ? rUser.bendahara : 0;
    var angAcc = rUser.anggota != null ? rUser.anggota : (totUsr - su - bd);
    return 'Terdapat ' + totUsr + ' akun pengguna terdaftar aktif yang terdiri dari ' + su + ' Super User, ' + bd + ' Bendahara, dan ' + angAcc + ' Anggota.';
  }

  // 17. AUDIT LOG (hanya super_user)
  var isAuditLog = (
    q.includes('audit') ||
    q.includes('log aktivitas') ||
    (q.includes('aktivitas') && (q.includes('terakhir') || q.includes('log') || q.includes('tercatat')))
  );
  if (isAuditLog) {
    if (role === 'anggota') {
      return 'Log aktivitas sistem hanya dapat diakses oleh Administrator (Super User).';
    }
    // Bendahara tidak punya akses ke log audit sistem
    if (role === 'bendahara') {
      return 'Log aktivitas sistem hanya dapat diakses oleh Administrator (Super User).';
    }
    if (!context.ringkasan_sistem || typeof context.ringkasan_sistem !== 'object') {
      return 'Mohon maaf, data log aktivitas belum tersedia dalam sistem kas.';
    }
    var totLog = sistem.total_audit_log != null ? sistem.total_audit_log : 0;
    var lastAkt = sistem.aktivitas_terakhir || null;
    var lastInfo = '';
    if (lastAkt && lastAkt.aktivitas && lastAkt.username) {
      lastInfo = ' Aktivitas terakhir: ' + lastAkt.aktivitas + ' oleh ' + lastAkt.username + (lastAkt.waktu ? ' pada ' + lastAkt.waktu : '') + '.';
    }
    return 'Tercatat ' + totLog + ' log aktivitas pada sistem.' + lastInfo;
  }

  // 18. TRANSAKSI UMUM
  if (q.includes('transaksi') || q.includes('riwayat')) {
    if (!context.ringkasan_transaksi || typeof context.ringkasan_transaksi !== 'object') {
      return 'Mohon maaf, data riwayat transaksi belum tersedia dalam sistem kas.';
    }
    return 'Setiap transaksi kas dicatat resmi oleh Administrator dan Bendahara (tercatat ' + (transaksi.total_transaksi_aktif||0) + ' transaksi aktif).';
  }

  // 19. KONDISI KAS
  if (q.includes('kondisi') || q.includes('surplus') || q.includes('defisit') || q.includes('banding') || q.includes('analisis') || q.includes('kesehatan')) {
    var surp = kas.surplus_defisit_bulan_ini != null ? kas.surplus_defisit_bulan_ini : 0;
    var saldoK = kas.saldo_kas_saat_ini != null ? kas.saldo_kas_saat_ini : 0;
    var stSurp = surp >= 0 ? 'surplus' : 'defisit';
    return 'Arus kas ' + periodeLaporan + ' berstatus ' + stSurp + ' sebesar ' + formatRupiah(Math.abs(surp)) + ', dengan saldo kas saat ini ' + formatRupiah(saldoK) + '.';
  }

  // 20. RINGKASAN UMUM (hanya 'ringkasan'/'laporan' -- BUKAN 'kas' saja!)
  if (q.includes('ringkasan') || q.includes('laporan')) {
    var sK = kas.saldo_kas_saat_ini != null ? kas.saldo_kas_saat_ini : 0;
    var pK = kas.pemasukan_bulan_ini != null ? kas.pemasukan_bulan_ini : 0;
    var pgK = kas.pengeluaran_bulan_ini != null ? kas.pengeluaran_bulan_ini : 0;
    var tgK = tunggakan.total_estimasi_tunggakan != null ? tunggakan.total_estimasi_tunggakan : 0;
    return 'Ringkasan kas ' + periodeLaporan + ': saldo ' + formatRupiah(sK) + ', pemasukan ' + formatRupiah(pK) + ', pengeluaran ' + formatRupiah(pgK) + ', estimasi tunggakan ' + formatRupiah(tgK) + '.';
  }

  return 'Mohon maaf, data tersebut belum tersedia dalam sistem kas.';
}

module.exports = { buildSystemPrompt, generateLocalAnalysis };
