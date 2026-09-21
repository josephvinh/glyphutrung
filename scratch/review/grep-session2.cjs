const z=require('node:zlib'),f=require('node:fs');
const buf=f.readFileSync(process.argv[1]);
// jsonl.zstd = nhiều frame zstd nối tiếp -> tách theo magic 28 B5 2F FD
const MAGIC=Buffer.from([0x28,0xB5,0x2F,0xFD]);
const idx=[]; for(let i=0;i<=buf.length-4;i++){ if(buf.compare(MAGIC,0,4,i,i+4)===0) idx.push(i); }
let out='';
for(let k=0;k<idx.length;k++){
  const end = k+1<idx.length ? idx[k+1] : buf.length;
  try { out += z.zstdDecompressSync(buf.subarray(idx[k],end)).toString('utf8'); } catch(e){}
}
console.log('So frame:', idx.length, '| Kich thuoc giai nen:', out.length);
for (const n of ['list_subagents','delegate','subagent-library','browser_goto','dsh_verify','chuyen-gia-toi-uu']) {
  console.log('  '+n.padEnd(20)+' -> '+(out.split(n).length-1)+' lan');
}
