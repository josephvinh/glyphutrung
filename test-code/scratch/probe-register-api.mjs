/**
 * Independent backend probe of the registration endpoint.
 * Covers the branch the delegated agent did not clearly exercise (danhXung=du_bi)
 * and captures the raw HTTP response plus the PHP/Apache error-log delta.
 */
const BASE = 'http://localhost:8888/tntt/public/api/auth.php?action=register';
const PHONE = '0999000777';

const payload = {
  holyName: 'Têrêsa',
  fullName: 'Kiểm Thử Dự Bị',
  phone: PHONE,
  birthDate: '1998-03-15',
  password: 'test123456',
  note: 'Probe nhánh Dự Bị',
  danhXung: 'du_bi',
};

const res = await fetch(BASE, {
  method: 'POST',
  headers: { 'content-type': 'application/json' },
  body: JSON.stringify(payload),
});

const text = await res.text();
console.log(`POST register (danhXung=du_bi) -> HTTP ${res.status}`);
console.log(`content-type: ${res.headers.get('content-type')}`);
console.log(`body: ${text.slice(0, 600)}`);

let parsed;
try {
  parsed = JSON.parse(text);
} catch {
  console.log('!! response is NOT valid JSON — a PHP warning/notice leaked into the body');
}
console.log(`parsed ok=${parsed?.ok} code=${parsed?.code ?? '-'} error=${parsed?.error ?? '-'}`);

// Same call again: must be rejected as a duplicate.
const dup = await fetch(BASE, {
  method: 'POST',
  headers: { 'content-type': 'application/json' },
  body: JSON.stringify(payload),
});
console.log(`\nsecond identical POST -> HTTP ${dup.status}: ${(await dup.text()).slice(0, 200)}`);
