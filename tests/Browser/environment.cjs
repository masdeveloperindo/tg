'use strict';
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..');
const parsed=new URL(process.env.QA_BASE_URL||'http://127.0.0.1:8081');
if(parsed.protocol!=='http:'||!['127.0.0.1','localhost','[::1]'].includes(parsed.hostname)||parsed.username||parsed.password||parsed.pathname!=='/'||parsed.search||parsed.hash)throw new Error('Browser QA requires an isolated HTTP loopback origin.');
const base=parsed.origin;
const artifacts=process.env.QA_ARTIFACT_DIR||path.resolve(root,'../.tg-cache');
module.exports={root,base,artifactPath:name=>path.join(artifacts,name),fixturePath:name=>path.join(root,'storage/framework/testing',name),chromiumPath:process.env.QA_BROWSER_EXECUTABLE||(fs.existsSync('/usr/bin/chromium')?'/usr/bin/chromium':undefined)};
