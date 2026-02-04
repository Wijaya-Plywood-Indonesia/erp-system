type Produksi = {
    name: string;
    dbListName: dbListProduksi
    urlResource: string

    total_produksi?: number;
    total_pegawai?: number;

    rekap_kualitas_ukuran?: {
        ukuran: string;
        jumlah: number;
        kualitas?: number;
    }[];
    rekap_ukuran?: {
        ukuran: string;
        jumlah: number;
    }[];

    addtional_info?: any;
}[]

type dbListProduksi = {
    name?: string;
    dbName: string;
    dbPegawaiName: string;
    dbHasilName: {
        name?: string;
        dbName: string;
        satuan_hasil: string;
        satuan_kualitas: string;

    }[];
}

export { Produksi, dbListProduksi };




const ListProduksi: Produksi = [
    {
        name: "Produksi Rotaries",
        urlResource: "produksi-rotaries",
        dbListName: {
            dbName: "produksi_rotaries",
            dbPegawaiName: "pegawai_rotaries",
            dbHasilName: [
                {
                    name: "Hasil Palet Rotaries",
                    dbName: "detail_hasil_palet_rotaries",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        },
        addtional_info: {
            description: "Custom group by mesin dan hitung jumlahnya",
            select: ["MERANTI", "MAHONI", "KAMARAS", "KEMPAS", "NYATUH", "ALBASIA"]
        }
    },
    {
        name: "Produksi Pot Siku",
        urlResource: "produksi-pot-siku",
        dbListName: {
            dbName: "produksi_pot_siku",
            dbPegawaiName: "pegawai_pot_siku",
            dbHasilName: [
                {
                    name: "Hasil Barang Dikerjakan Pot Siku",
                    dbName: "detail_barang_dikerjakan_pot_siku",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Pot Jelek",
        urlResource: "produksi-pot-jelek",
        dbListName: {
            dbName: "produksi_pot_jelek",
            dbPegawaiName: "pegawai_pot_jelek",
            dbHasilName: [
                {
                    name: "Hasil Barang Dikerjakan Pot Jelek",
                    dbName: "detail_barang_dikerjakan_pot_jelek",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Press Dryers",
        urlResource: "produksi-press-dryers",
        dbListName: {
            dbName: "produksi_press_dryers",
            dbPegawaiName: "detail_pegawais",
            dbHasilName: [
                {
                    name: "Hasil Press Dryers",
                    dbName: "detail_hasils",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Kedi",
        urlResource: "produksi-kedi",
        dbListName: {
            dbName: "produksi_kedi",
            dbPegawaiName: "detail_pegawai_kedi",
            dbHasilName: [
                {
                    name: "Hasil Masuk Kedi",
                    dbName: "detail_masuk_kedi",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Stik",
        urlResource: "produksi-stik",
        dbListName: {
            dbName: "produksi_stik",
            dbPegawaiName: "detail_pegawai_stik",
            dbHasilName: [
                {
                    name: "Hasil Stik",
                    dbName: "detail_hasil_stik",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Repair",
        urlResource: "produksi-repairs",
        dbListName: {
            dbName: "produksi_repairs",
            dbPegawaiName: "rencana_repairs",
            dbHasilName: [
                {
                    name: "Hasil Repairs",
                    dbName: "hasil_repairs",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Joint",
        urlResource: "produksi-joints",
        dbListName: {
            dbName: "produksi_joint",
            dbPegawaiName: "pegawai_joint",
            dbHasilName: [
                {
                    name: "Hasil Produksi Joint",
                    dbName: "hasil_joint",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Pot Af Joint",
        urlResource: "produksi-pot-af-joints",
        dbListName: {
            dbName: "produksi_pot_af_joint",
            dbPegawaiName: "pegawai_pot_af_joint",
            dbHasilName: [
                {
                    name: "Hasil Produksi Pot Af Joint",
                    dbName: "hasil_pot_af_joint",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Sanding Joint",
        urlResource: "produksi-sanding-joints",
        dbListName: {
            dbName: "produksi_sanding_joint",
            dbPegawaiName: "pegawai_sanding_joint",
            dbHasilName: [
                {
                    name: "Hasil Produksi Sanding Joint",
                    dbName: "hasil_sanding_joint",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Hot Press",
        urlResource: "produksi-hot-press",
        dbListName: {
            dbName: "produksi_hp",
            dbPegawaiName: "detail_pegawai_hp",
            dbHasilName: [
                {
                    name: "Hasil Platform Hot Press",
                    dbName: "platform_hasil_hp",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "grade",
                },
                {
                    name: "Hasil Triplek Hot Press",
                    dbName: "triplek_hasil_hp",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                },
            ]
        }
    },
    {
        name: "Produksi Graji Balken",
        urlResource: "produksi-graji-balken",
        dbListName: {
            dbName: "produksi_graji_balken",
            dbPegawaiName: "pegawai_graji_balken",
            dbHasilName: [
                {
                    name: "Hasil Graji Balken",
                    dbName: "hasil_graji_balken",
                    satuan_hasil: "Lembar", // Menyesuaikan satuan standar
                    satuan_kualitas: "jenis_kayu",
                }
            ]
        }
    },
    {
        name: "Produksi Guellotine",
        urlResource: "produksi-guellotine",
        dbListName: {
            dbName: "produksi_guellotine",
            dbPegawaiName: "pegawai_guellotine",
            dbHasilName: [
                {
                    name: "Hasil Guellotine",
                    dbName: "hasil_guellotine",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "jenis_kayu",
                }
            ]
        }
    },
    {
        name: "Produksi Pilih Veneer",
        urlResource: "produksi-pilih-veneer",
        dbListName: {
            dbName: "produksi_pilih_veneer",
            dbPegawaiName: "pegawai_pilih_veneer",
            dbHasilName: [
                {
                    name: "Hasil Pilih Veneer",
                    dbName: "hasil_pilih_veneer",
                    satuan_hasil: "Lembar",
                    satuan_kualitas: "kw",
                }
            ]
        }
    },
    {
        name: "Produksi Dempul",
        urlResource: "produksi-dempul",
        dbListName: {
            dbName: "produksi_dempuls",
            dbPegawaiName: "detail_dempul_pegawai",
            dbHasilName: [
                {
                    name: "Hasil Detail Dempul",
                    dbName: "detail_dempul",
                    satuan_hasil: "Pcs",
                    satuan_kualitas: "grade",
                }
            ]
        },
        addtional_info: {
            key_satuan_kualitas: "$b->grade->nama_grade",
            catatan: "Evaluasi kategori, ukuran, grade, dan jenis barang."
        }
    },
    {
        name: "Produksi Graji Triplek",
        urlResource: "produksi-graji-triplek",
        dbListName: {
            dbName: "produksi_graji_triplek",
            dbPegawaiName: "pegawai_graji_triplek",
            dbHasilName: [
                {
                    name: "Hasil Graji Triplek",
                    dbName: "hasil_graji_triplek",
                    satuan_hasil: "Pcs",
                    satuan_kualitas: "grade",
                }
            ]
        }
    },
    {
        name: "Produksi Nyusup",
        urlResource: "produksi-nyusup",
        dbListName: {
            dbName: "produksi_nyusup",
            dbPegawaiName: "pegawai_nyusup",
            dbHasilName: [
                {
                    name: "Hasil Nyusup",
                    dbName: "detail_barang_dikerjakan",
                    satuan_hasil: "Pcs",
                    satuan_kualitas: "grade",
                }
            ]
        }
    },
    {
        name: "Produksi Pilih Plywoods",
        urlResource: "produksi-pilih-plywood",
        dbListName: {
            dbName: "produksi_pilih_plywood",
            dbPegawaiName: "pegawai_pilih_plywood",
            dbHasilName: [
                {
                    name: "Hasil Pilih Plywood",
                    dbName: "hasil_pilih_plywood",
                    satuan_hasil: "Pcs",
                    satuan_kualitas: "grade",
                }
            ]
        }
    }
];
// const ListProduksi: Produksi = [
//     {
//         name: "Produksi Rotaries",
//         urlResource: "produksi-rotaries",
//         dbListName: {
//             dbName: "produksi_rotaries",
//             dbPegawaiName: "pegawai_rotaries",
//             dbHasilName: [
//                 {
//                     name: "Hasil Palet Rotaries",
//                     dbName: "detail_hasil_palet_rotaries",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "kw",
//                 }
//             ]
//         },
//         addtional_info: {
//             description: "ini harus custom, karena nanti dibuat permesin data nya, seperti group by mesin dan hitung jumlahnya",
//             select: ["MERANTI", "MAHONI", "KAMARAS", "KEMPAS", "NYATUH", "ALBASIA"]
//         }
//     },
//     {
//         name: "Produksi Pot Siku",
//         dbListName: {
//             dbName: "produksi_pot_siku",
//             dbPegawaiName: "pegawai_pot_siku",
//             dbHasilName: [
//                 {
//                     name: "Hasil Barang Dikerjakan Pot Siku",
//                     dbName: "detail_barang_dikerjakan_pot_siku",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "kw",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Pot Jelek",
//         dbListName: {
//             dbName: "produksi_pot_jelek",
//             dbPegawaiName: "pegawai_pot_jelek",
//             dbHasilName: [
//                 {
//                     name: "Hasil Barang Dikerjakan Pot Jelek",
//                     dbName: "detail_barang_dikerjakan_pot_jelek",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "kw",
//                 }
//             ]
//         }
//     },

//     {
//         name: "Produksi Press Dryers",
//         dbListName: {
//             dbName: "produksi_press_dryers",
//             dbPegawaiName: "detail_pegawais",
//             dbHasilName: [
//                 {
//                     name: "Hasil Press Dryers",
//                     dbName: "detail_hasils",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "kw",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Kedi",
//         dbListName: {
//             dbName: "produksi_kedi",
//             dbPegawaiName: "detail_pegawai_kedi",
//             dbHasilName: [
//                 {
//                     name: "Hasil Masuk Kedi",
//                     dbName: "detail_masuk_kedi",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "kw",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Stik",
//         dbListName: {
//             dbName: "produksi_stik",
//             dbPegawaiName: "detail_pegawai_stik",
//             dbHasilName: [
//                 {
//                     name: "Hasil Stik",
//                     dbName: "detail_hasil_stik",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "kw",
//                 }
//             ]
//         }
//     },


//     {
//         name: "Produksi Hot Press",
//         dbListName: {
//             dbName: "produksi_hp",
//             dbPegawaiName: "detail_pegawai_hp",
//             dbHasilName: [
//                 {
//                     name: "Hasil Platform Hot Press",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "grade",
//                     dbName: "platform_hasil_hp"
//                 },
//                 {
//                     name: "Hasil Triplek Hot Press",
//                     dbName: "triplek_hasil_hp",
//                     satuan_kualitas: "kw",
//                     satuan_hasil: "Lembar"
//                 },
//             ]
//         }
//     },
//     {
//         name: "Produksi Graji Balken",
//         dbListName: {
//             dbName: "produksi_graji_balken",
//             dbPegawaiName: "pegawai_graji_balken",
//             dbHasilName: [
//                 {
//                     name: "Hasil Graji Balken",
//                     dbName: "hasil_graji_balken",
//                     satuan_hasil: "Pcs",
//                     satuan_kualitas: "jenis_kayu",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Graji Balken",
//         dbListName: {
//             dbName: "produksi_graji_balken",
//             dbPegawaiName: "pegawai_graji_balken",
//             dbHasilName: [
//                 {
//                     name: "Hasil Graji Balken",
//                     dbName: "hasil_graji_balken",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "jenis_kayu",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Guellotine",
//         dbListName: {
//             dbName: "produksi_guellotine",
//             dbPegawaiName: "pegawai_guellotine",
//             dbHasilName: [
//                 {
//                     name: "Hasil Guellotine",
//                     dbName: "hasil_guellotine",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "jenis_kayu",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Pilih Veneer",
//         dbListName: {
//             dbName: "produksi_pilih_veneer",
//             dbPegawaiName: "pegawai_pilih_veneer",
//             dbHasilName: [
//                 {
//                     name: "Hasil Pilih Veneer",
//                     dbName: "hasil_pilih_veneer",
//                     satuan_hasil: "Lembar",
//                     satuan_kualitas: "kw",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Dempul",
//         dbListName: {
//             dbName: "produksi_dempuls",
//             dbPegawaiName: "detail_dempul_pegawai",
//             dbHasilName: [
//                 {
//                     name: "Hasil Detail Dempul",
//                     dbName: "detail_dempul",
//                     satuan_hasil: "Pcs",
//                     satuan_kualitas: "grade",
//                 }
//             ]
//         },
//         addtional_info: {
//             key_satuan_kualitas: "$b->grade->nama_grade",
//             catatan: `                        $b = $record->barangSetengahJadi;
//                         if (!$b) return '-';

//                         return ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
//                             ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
//                             ($b->grade?->nama_grade ?? '-') . ' | ' .
//                             ($b->jenisBarang?->nama_jenis_barang ?? '-');
// `,

//         }
//     },
//     {
//         name: "Produksi Graji Triplek",
//         dbListName: {
//             dbName: "produlsi_graji_triplek",
//             dbPegawaiName: "pegawai_graji_triplek",
//             dbHasilName: [
//                 {
//                     name: "Hasil Graji Triplek",
//                     dbName: "hasil_graji_triplek",
//                     satuan_hasil: "Pcs",
//                     satuan_kualitas: "grade",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Graji Triplek",
//         dbListName: {
//             dbName: "produksi_graji_triplek",
//             dbPegawaiName: "pegawai_graji_triplek",
//             dbHasilName: [
//                 {
//                     name: "Hasil Graji Triplek",
//                     dbName: "hasil_graji_triplek",
//                     satuan_hasil: "Pcs",
//                     satuan_kualitas: "grade",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Nyusup",
//         dbListName: {
//             dbName: "produksi_nyusup",
//             dbPegawaiName: "pegawai_nyusup",
//             dbHasilName: [
//                 {
//                     name: "Hasil Graji Triplek",
//                     dbName: "detail_barang_dikerjakan",
//                     satuan_hasil: "Pcs",
//                     satuan_kualitas: "grade",
//                 }
//             ]
//         }
//     },
//     {
//         name: "Produksi Pilih Plywoods",
//         dbListName: {
//             dbName: "produksi_pilih_plywood",
//             dbPegawaiName: "pegawai_pilih_plywood",
//             dbHasilName: [
//                 {
//                     name: "Hasil Graji Triplek",
//                     dbName: "hasil_pilih_plywood",
//                     satuan_hasil: "Pcs",
//                     satuan_kualitas: "grade",
//                 }
//             ]
//         }
//     },
// ]




//   {
//     "name": "Produksi Repair",
//     "urlResource": "produksi-repairs",
//     "dbListName": {
//       "dbName": "produksi_repairs",
//       "dbPegawaiName": "rencana_repairs",
//       "dbHasilName": [
//         {
//           "name": "Hasil Repairs",
//           "dbName": "hasil_repairs",
//           "satuan_hasil": "Lembar",
//           "satuan_kualitas": "kw",
//           "key_jumlah": "jumlah",
//           "key_ukuran": ""
//         }
//       ]
//     }
//   },


// {
//     "name": "Produksi Hot Press",
//     "urlResource": "produksi-hot-press",
//     "dbListName": {
//       "dbName": "produksi_hp",
//       "dbPegawaiName": "detail_pegawai_hp",
//       "dbHasilName": [
//         {
//           "name": "Hasil Platform Hot Press",
//           "dbName": "platform_hasil_hp",
//           "satuan_hasil": "Lembar",
//           "satuan_kualitas": "grade",
//           "key_jumlah": "isi"
//         },
//         {
//           "name": "Hasil Triplek Hot Press",
//           "dbName": "triplek_hasil_hp",
//           "satuan_hasil": "Lembar",
//           "satuan_kualitas": "kw",
//           "key_jumlah": "jumlah"
//         }
//       ]
//     }
//   },



// {
//     "name": "Produksi Pilih Veneer",
//     "urlResource": "produksi-pilih-veneer",
//     "dbListName": {
//       "dbName": "produksi_pilih_veneer",
//       "dbPegawaiName": "pegawai_pilih_veneer",
//       "dbHasilName": [
//         {
//           "name": "Hasil Pilih Veneer",
//           "dbName": "hasil_pilih_veneer",
//           "satuan_hasil": "Lembar",
//           "satuan_kualitas": "kw",
//           "key_jumlah": "jumlah"
//         }
//       ]
//     }
//   },

//   {
//     "name": "Produksi Dempul",
//     "urlResource": "produksi-dempul",
//     "dbListName": {
//       "dbName": "produksi_dempuls",
//       "dbPegawaiName": "detail_dempul_pegawai",
//       "dbHasilName": [
//         {
//           "name": "Hasil Detail Dempul",
//           "dbName": "detail_dempuls",
//           "satuan_hasil": "Pcs",
//           "satuan_kualitas": "grade",
//           "key_jumlah": "hasil"
//         }
//       ]
//     },
//     "addtional_info": {
//       "key_satuan_kualitas": "$b->grade->nama_grade",
//       "catatan": "Evaluasi kategori, ukuran, grade, dan jenis barang."
//     }
//   },

//   {
//     "name": "Produksi Graji Triplek",
//     "urlResource": "produksi-graji-triplek",
//     "dbListName": {
//       "dbName": "produksi_graji_triplek",
//       "dbPegawaiName": "pegawai_graji_triplek",
//       "dbHasilName": [
//         {
//           "name": "Hasil Graji Triplek",
//           "dbName": "hasil_graji_triplek",
//           "satuan_hasil": "Pcs",
//           "satuan_kualitas": "grade",
//           "key_jumlah": "isi"
//         }
//       ]
//     }
//   },

//   {
//     "name": "Produksi Nyusup",
//     "urlResource": "produksi-nyusup",
//     "dbListName": {
//       "dbName": "produksi_nyusup",
//       "dbPegawaiName": "pegawai_nyusup",
//       "dbHasilName": [
//         {
//           "name": "Hasil Nyusup",
//           "dbName": "detail_barang_dikerjakan",
//           "satuan_hasil": "Pcs",
//           "satuan_kualitas": "grade",
//           "key_jumlah": "hasil"
//         }
//       ]
//     }
//   },
//   {
//     "name": "Produksi Pilih Plywoods",
//     "urlResource": "produksi-pilih-plywood",
//     "dbListName": {
//       "dbName": "produksi_pilih_plywood",
//       "dbPegawaiName": "pegawai_pilih_plywood",
//       "dbHasilName": [
//         {
//           "name": "Hasil Bagus",
//           "dbName": "hasil_pilih_plywood",
//           "satuan_hasil": "Pcs",
//           "satuan_kualitas": "grade",
//           "key_jumlah": "jumlah_bagus"
//         },
//         {
//           "name": "Hasil Cacat",
//           "dbName": "hasil_pilih_plywood",
//           "satuan_hasil": "Pcs",
//           "satuan_kualitas": "grade",
//           "key_jumlah": "jumlah"
//         }
//       ]
//     }
//   }