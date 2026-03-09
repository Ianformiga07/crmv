    </main><!-- /pagina -->
</div><!-- /main-wrapper -->

<!-- Overlay mobile -->
<div id="sidebar-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999" onclick="fecharSidebar()"></div>

<script>
// Toggle sidebar mobile
var sidebar = document.getElementById('sidebar');
var overlay = document.getElementById('sidebar-overlay');
document.getElementById('sidebar-toggle').addEventListener('click', function(){
    sidebar.classList.toggle('aberta');
    overlay.style.display = sidebar.classList.contains('aberta') ? 'block' : 'none';
});
function fecharSidebar(){
    sidebar.classList.remove('aberta');
    overlay.style.display = 'none';
}
// Confirmar ações
document.querySelectorAll('[data-confirma]').forEach(function(el){
    el.addEventListener('click', function(e){
        if(!confirm(this.dataset.confirma)) e.preventDefault();
    });
});
</script>
</body>
</html>
