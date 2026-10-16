<?php

/*
| Angkatan mahasiswa ditentukan dari awalan NIM.
| Keputusan pemilik aplikasi 16-10-2026:
|   2023 = K3523, 2024 = K3524, 2025 = K3525,
|   2026 = 511061026, ditambah 211062326 dan 211082326
|   (NIM 14 digit yang sudah ada di produksi).
| Pencocokan tidak membedakan huruf besar/kecil.
| Angkatan baru cukup ditambahkan di sini, urut dari yang terbaru.
*/

return [
    'prefixes' => [
        '2026' => ['511061026', '211062326', '211082326'],
        '2025' => ['K3525'],
        '2024' => ['K3524'],
        '2023' => ['K3523'],
    ],
];
