<?php
require_once __DIR__.'/offline_runtime.php';casa_auth();
$gate=(string)($_GET['casa_gate']??'');$entry=$_SESSION['casa_gates'][$gate]??null;
$bypass=isset($_GET['casa_unverified']);
if($entry&&time()-(int)$entry['started']<1800&&(!empty($entry['verified'])||($bypass&&time()-(int)$entry['started']>=6))) {
    unset($_SESSION['casa_gates'][$gate]);
    $GLOBALS['casa_unverified']=$bypass;
    return;
}
foreach(($_SESSION['casa_gates']??[]) as $key=>$old)if(time()-(int)$old['started']>1800)unset($_SESSION['casa_gates'][$key]);
$gate=bin2hex(random_bytes(16));$_SESSION['casa_gates'][$gate]=['started'=>time(),'after'=>0];
include __DIR__.'/header.php';
?><main class="container-fluid"><div class="card p-4"><h1 class="h4">Verificare facturi online</h1><p id="check-message">Se preiau facturile și statusurile ANAF înainte de afișarea listei.</p><p id="check-warning" class="alert alert-warning" hidden>Verificarea nu este confirmată. Verificați cu mare atenție seria și ultimul număr facturat online înainte de a emite o factură. Fără internet, numărul nu poate fi rezervat online și poate intra în conflict cu o factură emisă de alt utilizator.</p><div><button id="check-retry" class="btn btn-primary" hidden>Reîncearcă verificarea</button> <button id="check-skip" class="btn btn-outline-danger" hidden>Continuă fără verificare</button></div></div></main>
<script>
(function(){
 const gate=<?=json_encode($gate)?>;let busy=false,count=0;
 const msg=document.getElementById('check-message'),skip=document.getElementById('check-skip'),retry=document.getElementById('check-retry'),warning=document.getElementById('check-warning');
 function openList(unverified){const url=new URL(location.href);url.searchParams.set('casa_gate',gate);url.searchParams.delete('casa_unverified');if(unverified)url.searchParams.set('casa_unverified','1');location.replace(url.href);}
 async function run(){if(busy)return;busy=true;retry.hidden=true;
  try {const catalog=await fetch('pull_online.php',{method:'POST',body:new URLSearchParams({action:'company'})});const company=await catalog.json();if(!company.success)throw new Error(company.error);let done=false;while(!done){const body=new URLSearchParams({action:'invoices',gate:gate});const response=await fetch('pull_online.php',{method:'POST',body:body});const data=await response.json();if(!data.success)throw new Error(data.error);count+=data.count;msg.textContent='Facturi verificate: '+count+'. Se preiau paginile rămase.';done=data.done;}openList(false);}
  catch(e){msg.textContent=e.message||'Conexiune indisponibilă.';warning.hidden=false;retry.hidden=false;}
  finally{busy=false;}
 }
 setTimeout(function(){skip.hidden=false;warning.hidden=false;},6500);
 skip.onclick=function(){if(confirm('Continuați fără confirmarea ultimului număr facturat online? Verificați manual seria și numărul înainte de emitere.'))openList(true);};retry.onclick=run;run();
})();
</script><?php include __DIR__.'/footer.php';exit;
