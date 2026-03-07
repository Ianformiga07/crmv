    </main>
</div>

<div class="toast-area" id="toastArea"></div>
<div id="overlayMobile" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999"></div>

<script>
// Sidebar mobile
var sidebar  = document.getElementById('sidebar');
var btnSB    = document.getElementById('btnSidebar');
var overlay  = document.getElementById('overlayMobile');
if (btnSB) btnSB.addEventListener('click', function() {
    var ab = sidebar.classList.toggle('aberta');
    overlay.style.display = ab ? 'block' : 'none';
});
if (overlay) overlay.addEventListener('click', function() {
    sidebar.classList.remove('aberta');
    overlay.style.display = 'none';
});

// Tabs
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var alvo = this.getAttribute('data-tab');
        var cont = this.closest('.tabs-container') || document;
        cont.querySelectorAll('.tab-btn').forEach(function(b)    { b.classList.remove('ativo'); });
        cont.querySelectorAll('.tab-painel').forEach(function(p) { p.classList.remove('ativo'); });
        this.classList.add('ativo');
        var p = cont.querySelector('#' + alvo);
        if (p) p.classList.add('ativo');
    });
});

// Confirmação
document.querySelectorAll('[data-confirma]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (!confirm(this.getAttribute('data-confirma'))) e.preventDefault();
    });
});

// Toasts
window.showToast = function(msg, tipo, ms) {
    tipo = tipo || 'sucesso'; ms = ms || 4000;
    var icons = {sucesso:'fa-circle-check', erro:'fa-circle-xmark', aviso:'fa-triangle-exclamation'};
    var t = document.createElement('div');
    t.className = 'toast t-' + tipo;
    t.innerHTML = '<i class="fa-solid ' + (icons[tipo]||'fa-info-circle') + '"></i><span>' + msg + '</span>';
    document.getElementById('toastArea').appendChild(t);
    setTimeout(function() {
        t.style.transition = 'all .3s'; t.style.opacity = '0'; t.style.transform = 'translateY(10px)';
        setTimeout(function() { t.remove(); }, 300);
    }, ms);
};

// Auto-fechar flash
var flash = document.getElementById('flashMsg');
if (flash) setTimeout(function() {
    flash.style.transition = 'opacity .5s'; flash.style.opacity = '0';
    setTimeout(function() { flash.remove(); }, 500);
}, 6000);

// Máscaras
function maskCPF(v) {
    v = v.replace(/\D/g,'').slice(0,11);
    return v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
}
function maskTel(v) {
    v = v.replace(/\D/g,'').slice(0,11);
    return v.length===11 ? v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3') : v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3');
}
function maskCEP(v) {
    v = v.replace(/\D/g,'').slice(0,8);
    return v.replace(/(\d{5})(\d)/,'$1-$2');
}
document.querySelectorAll('[data-mask="cpf"]').forEach(function(el) { el.addEventListener('input',function(){ this.value=maskCPF(this.value); }); });
document.querySelectorAll('[data-mask="tel"]').forEach(function(el) { el.addEventListener('input',function(){ this.value=maskTel(this.value); }); });
document.querySelectorAll('[data-mask="cep"]').forEach(function(el) {
    el.addEventListener('input', function() {
        this.value = maskCEP(this.value);
        if (this.value.replace(/\D/g,'').length===8) buscaCEP(this.value);
    });
});
function buscaCEP(cep) {
    cep = cep.replace(/\D/g,'');
    if (cep.length!==8) return;
    fetch('https://viacep.com.br/ws/'+cep+'/json/').then(r=>r.json()).then(d=>{
        if (d.erro) return;
        var f = function(n,v){var el=document.querySelector('[name="'+n+'"]');if(el&&v)el.value=v;};
        f('logradouro',d.logradouro); f('bairro',d.bairro); f('cidade',d.localidade); f('uf',d.uf);
    }).catch(()=>{});
}

// Guard de formulário sujo
var formSujo = false;
document.querySelectorAll('form[data-guard]').forEach(function(f) {
    f.addEventListener('change',function(){ formSujo=true; });
    f.addEventListener('submit',function(){ formSujo=false; });
});
window.addEventListener('beforeunload', function(e) { if (formSujo){ e.preventDefault(); e.returnValue=''; } });

// Contador de caracteres
document.querySelectorAll('[data-max]').forEach(function(el) {
    var max = parseInt(el.getAttribute('data-max'));
    var cnt = document.createElement('span');
    cnt.style.cssText = 'font-size:.72rem;color:var(--c400);float:right;margin-top:2px';
    el.parentNode.appendChild(cnt);
    function upd(){ var r=max-el.value.length; cnt.textContent=r+' restantes'; cnt.style.color=r<20?'var(--verm)':'var(--c400)'; }
    el.addEventListener('input',upd); upd();
});
</script>
</body>
</html>
