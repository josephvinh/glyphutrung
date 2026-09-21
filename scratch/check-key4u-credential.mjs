// Decide whether the key4u route can authenticate.
// Never prints the secret — only booleans and lengths.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;

const settings = yaml.load(fs.readFileSync(`${H}/settings.yaml`, 'utf8'));
const creds = yaml.load(fs.readFileSync(`${H}/.credentials.yaml`, 'utf8'));

const refUsed = String(settings['llm-pi-ai'].providers.key4u.apiKeyEnv);
const storedRefNames = Object.keys(creds.refs ?? {});
const storedRefValues = Object.values(creds.refs ?? {});

console.log(`apiKeyEnv (the ref the route resolves): ${refUsed.slice(0, 3)}***${refUsed.slice(-4)} (len ${refUsed.length})`);
console.log(`stored ref NAMES: ${JSON.stringify(storedRefNames)}`);
console.log('');
console.log(`ref name exists in store?      ${storedRefNames.includes(refUsed)}`);
console.log(`refUsed is a literal secret?   ${refUsed.startsWith('sk-')}`);
console.log(`refUsed matches a stored VALUE? ${storedRefValues.some((v) => v === refUsed)}`);
console.log(`launch env has that var name?  ${process.env[refUsed] !== undefined}`);
console.log('');
console.log('=> route resolution: credentials.resolve(refUsed) then env[refUsed]');
console.log(`=> would find a credential?    ${storedRefNames.includes(refUsed) || process.env[refUsed] !== undefined}`);
console.log('');
console.log('working ref name to use instead:', storedRefNames[0] ?? '(none)');
