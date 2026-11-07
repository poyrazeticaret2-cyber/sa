#!/usr/bin/env python3
"""Bölüm parçalarını (parts/*.html) tek index.html'e birleştirir.
Kullanım: python3 build.py [çıktı_yolu]   (varsayılan: index.html)
Her parça: isteğe bağlı <style>...</style> + kök elemanı data-sec="<ad>" olan markup.
"""
import sys, os, re
HERE = os.path.dirname(os.path.abspath(__file__))
ORDER = ["hero", "pair", "comfort", "plan", "final"]
out_path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "index.html")

base_css = open(os.path.join(HERE, "base.css"), encoding="utf-8").read()
styles, bodies = [], []
for name in ORDER:
    p = os.path.join(HERE, "parts", name + ".html")
    if not os.path.exists(p):
        bodies.append(f'<section data-sec="{name}" style="padding:40px;text-align:center;color:#999">[{name} parçası yok]</section>')
        continue
    src = open(p, encoding="utf-8").read()
    for m in re.finditer(r"<style[^>]*>(.*?)</style>", src, re.S):
        styles.append(f"/* ---- {name} ---- */\n" + m.group(1).strip())
    body = re.sub(r"<style[^>]*>.*?</style>", "", src, flags=re.S).strip()
    bodies.append(body)

html = f"""<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Siyah Chelsea Bot – Her Siparişe Ücretsiz Bakım Seti</title>
<meta name="description" content="Hakiki deri siyah Chelsea bot. İş, seyahat ve günlük kullanım için tek çift. Kara Cuma'da her siparişe ücretsiz deri bakım seti.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
{base_css}
{chr(10).join(styles)}
</style>
</head>
<body>
<main class="page">
{chr(10).join(bodies)}
</main>
<script>
const io=new IntersectionObserver(es=>es.forEach(e=>{{if(e.isIntersecting){{e.target.classList.add('in');io.unobserve(e.target)}}}}),{{threshold:.12}});
document.querySelectorAll('.rv').forEach(el=>io.observe(el));
document.querySelectorAll('[data-year]').forEach(el=>el.textContent=new Date().getFullYear());
</script>
</body>
</html>
"""
os.makedirs(os.path.dirname(out_path) or ".", exist_ok=True)
open(out_path, "w", encoding="utf-8").write(html)
print("yazıldı:", out_path, "| parçalar:", ", ".join(n for n in ORDER if os.path.exists(os.path.join(HERE, "parts", n + ".html"))))
