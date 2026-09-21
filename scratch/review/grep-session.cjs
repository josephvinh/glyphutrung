const z=require('node:zlib'),f=require('node:fs');
const t=z.zstdDecompressSync(f.readFileSync(process.argv[1])).toString('utf8');
console.log('Kich thuoc transcript:', t.length);
for (const n of ['list_subagents','delegate','subagent-library','browser_goto','dsh_verify']) {
  console.log('  '+n+' -> '+(t.split(n).length-1)+' lan');
}
