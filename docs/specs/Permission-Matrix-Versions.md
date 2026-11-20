# Permission matrix versions

The signed matrix is `database/seeders/data/permission_matrix.csv`. A change to it needs a new signed version, recorded here.
Blueprint Appendix B carries each version (version 3 added `sr.export` on 2026-11-20). If the signed workbook differs, the workbook wins.

| Version | Date | Change | Signed |
|---|---|---|---|
| Draft 2 | 2026-10-17 | The matrix of blueprint Appendix B: 42 keys. Signed off with no changes (workbook `Aiywah-FSM-Permission-Matrix-Draft-2.xlsx`). | Kiran E (BA - PM) |
| Version 3 | 2026-11-20 | Added `sr.export` (group Service requests): "Download the request list as an Excel file". Full for Company Admin and Operations Manager, none for Team Lead, Field Technician, Front Desk, Finance and Client User. Reason: blueprint 7.9a says only the Company Admin and the Operations Manager export the request list. The migration `2026_11_27_100001_add_sr_export_permission` gives the key to companies that already exist. 43 keys. | Owner, 2026-11-20 |

Check on 2026-11-20 against Appendix B before it was updated: the only difference was `sr.export`. The levels of the 42 other keys were identical for all seven roles. Appendix B now has `sr.export` too (the 43 keys match).
