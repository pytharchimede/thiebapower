# Standard-library HTTP checks against the isolated fixture from station_experience_database.php.
import urllib.request,urllib.error,urllib.parse,http.cookiejar,json as jsonlib
ConnectionError=urllib.error.URLError
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args): return None
class Response:
 def __init__(self,r): self.status_code=r.code;self.text=r.read().decode(errors='replace')
 def json(self): return jsonlib.loads(self.text)
class Session:
 def __init__(self): self.jar=http.cookiejar.CookieJar()
 def request(self,method,url,data=None,json=None,allow_redirects=True):
  handlers=[urllib.request.HTTPCookieProcessor(self.jar)]
  if not allow_redirects: handlers.append(NoRedirect())
  opener=urllib.request.build_opener(*handlers);headers={};body=None
  if json is not None: body=jsonlib.dumps(json).encode();headers['Content-Type']='application/json'
  elif data is not None: body=urllib.parse.urlencode(data).encode()
  try: return Response(opener.open(urllib.request.Request(url,body,headers,method=method),timeout=10))
  except urllib.error.HTTPError as e: return Response(e)
 def get(self,url,**kw): return self.request('GET',url,**kw)
 def post(self,url,**kw): return self.request('POST',url,**kw)


import re,time
import os,types
base=os.getenv('TEST_BASE_URL','http://127.0.0.1:8090').rstrip('/')
if not base.startswith(('http://127.0.0.1:','http://localhost:')): raise RuntimeError('Local test server required')
requests=types.SimpleNamespace(Session=Session,ConnectionError=ConnectionError,get=Session().get,post=Session().post)
for _ in range(30):
 try:
  if requests.get(base+'/stations/map').status_code==200: break
 except requests.ConnectionError: time.sleep(.1)
public=requests.get(base+'/stations/snapshot');assert public.status_code==200,public.text
rows=public.json()['stations'];assert len(rows)==2 and rows[0]['imei']=='TEST01';assert rows[0]['manager_name']=='SECRET MANAGER' and rows[0]['manager_phone']=='0700000000';assert rows[1]['enabled'] is False;assert 'manager_email' not in public.text and 'manager_notes' not in public.text and 'investment' not in public.text
assert requests.get(base+'/my-rentals/snapshot').status_code==404
unknown=requests.post(base+'/my-rentals/snapshot',json={'tokens':['b'*32]});assert unknown.json()=={'rentals':[]}
token='1'+'a'*31
owned=requests.post(base+'/my-rentals/snapshot',json={'tokens':[token]});assert len(owned.json()['rentals'])==1;assert 'customer_phone' not in owned.text
assert requests.post(base+'/my-rentals/support',data={'token':token,'issue':'return','message':'Hello'}).status_code==415
support=requests.post(base+'/my-rentals/support',json={'token':token,'issue':'return','message':'Je souhaite vérifier mon retour.'});assert support.status_code==201,support.text
assert requests.post(base+'/my-rentals/support',json={'token':token,'issue':'return','message':'Deuxième demande'}).status_code==409

def login(name):
 s=requests.Session();page=s.get(base+'/admin/login');csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1);r=s.post(base+'/admin/login',data={'username':name,'password':'local-test-password','csrf':csrf},allow_redirects=False)
 assert r.status_code==303,r.text
 return s
owner=login('owner');page=owner.get(base+'/admin/stations/profile?imei=TEST01');assert page.status_code==200,page.text
csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1)
bad=owner.post(base+'/admin/stations/profile',data={'imei':'TEST01','label':'Bad','latitude':99,'longitude':-4,'csrf':csrf});assert bad.status_code==422
saved=owner.post(base+'/admin/stations/profile',data={'imei':'TEST01','label':'Thieba Power Café','latitude':5.35,'longitude':-4.01,'address':'Koumassi, entrée principale','manager_name':'SECRET MANAGER 2','manager_phone':'0700000000','manager_email':'gerant@example.ci','csrf':csrf},allow_redirects=False);assert saved.status_code==303,saved.text
rows=requests.get(base+'/stations/snapshot').json()['stations'];assert rows[0]['label']=='Thieba Power Café' and rows[0]['latitude']==5.35 and rows[0]['available']==2
for path in ['/admin/stations/profitability','/admin/promotions','/admin/rentals/watch','/admin/support','/admin/batteries/detail?id=1']:
 r=owner.get(base+path);assert r.status_code==200,(path,r.text)
assert owner.post(base+'/admin/stations/profile',data={'imei':'TEST01','label':'Oops'}).status_code==403
reader=login('auditor');page=reader.get(base+'/admin/stations/profile?imei=TEST01');assert page.status_code==200 and 'data-editable="0"' in page.text
csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1)
assert reader.post(base+'/admin/stations/profile',data={'imei':'TEST01','label':'Oops','csrf':csrf}).status_code==403
assert reader.get(base+'/admin/stations/profitability').status_code==403
assert reader.get(base+'/admin/rentals/watch').status_code==200
print('HTTP integration: public privacy, customer bearer tokens, support idempotence, profile validation, CSRF, permissions and admin pages OK')

assert reader.get(base+'/admin/public-settings').status_code==403
assert owner.post(base+'/admin/public-settings',data={'support_enabled':'on'}).status_code==403
page=owner.get(base+'/admin/public-settings');assert page.status_code==200,page.text
csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1)
params={'csrf':csrf,'support_enabled':'on','whatsapp_enabled':'on','whatsapp':'+2250700000000','message':'Aide location','visible[logo]':'on'}
saved=owner.post(base+'/admin/public-settings',data=params,allow_redirects=False);assert saved.status_code==303,saved.text
page=requests.get(base+'/stations/map');assert 'https://wa.me/2250700000000' in page.text and '<details class="public-support">' in page.text
rows=requests.get(base+'/stations/snapshot').json()['stations'];assert not rows[0]['manager_name'] and not rows[0]['manager_phone']
params={'csrf':csrf};params.update({'visible['+key+']':'on' for key in ['logo','slogan','locations','stations','intro','steps','illustration','price_teaser','payment_logos','offers','manager_name','manager_phone','hours','stock','summary','incidents','release','footer']})
assert owner.post(base+'/admin/public-settings',data=params,allow_redirects=False).status_code==303
assert '<details class="public-support">' not in requests.get(base+'/stations/map').text
print('Public support HTTP: permission, CSRF, saved WhatsApp channel, hidden manager fields and disabled widget OK')

page=owner.get(base+'/admin/training');assert page.status_code==200 and '20' in page.text,page.text
csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1)
assert reader.get(base+'/admin/training').status_code==200
assert reader.post(base+'/admin/training/proposals',data={'csrf':csrf,'title':'Interdit'}).status_code==403
assert owner.post(base+'/admin/training/proposals',data={'title':'Test'}).status_code==403
payload={'csrf':csrf,'title':'Chat <script>test</script>','description':'Échanges clients liés aux locations','benefit':'Suivi des échanges','scope':'Boîte opérateur et historique','status':'proposed','priority':'high','budget':'120000','timeframe':'À étudier'}
r=owner.post(base+'/admin/training/proposals',data=payload,allow_redirects=False);assert r.status_code==303,r.text
page=owner.get(base+'/admin/training');assert '&lt;script&gt;test&lt;/script&gt;' in page.text and '<script>test</script>' not in page.text
assert owner.get(base+'/admin/training/pdf?kind=guide').text.startswith('%PDF-1.4')
assert owner.get(base+'/admin/training/pdf?kind=proposals&id=1').text.startswith('%PDF-1.4')
assert owner.get(base+'/admin/training/pdf?kind=proposals&id=9999').status_code==404
payload.update({'id':'1','status':'approved'});assert owner.post(base+'/admin/training/proposals',data=payload,allow_redirects=False).status_code==303
print('Training HTTP: access, CSRF, proposal create/edit, escaping and guide/proposal PDF exports OK')

page=owner.get(base+'/admin/training');assert 'Thiebapower v1.0.0' in page.text and 'Développé par' in page.text and 'Success’Lab' in page.text
page=requests.get(base+'/stations/map');assert 'Thiebapower v1.0.0' in page.text and 'Success’Lab' in page.text
print('Release footer HTTP: public and dashboard version and developer credit OK')
# Independent flags and lifecycle operations on local fixtures, without payment calls.
page=owner.get(base+'/admin/promotions');assert page.status_code==200,page.text
csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1)
payload={'csrf':csrf,'code':'LIFECYCLE','kind':'campaign','discount':200,'max_uses':5,'minimum_completed':0,'start':'2020-01-01','end':'2099-12-31','enabled':'on'}
assert owner.post(base+'/admin/promotions',data=payload,allow_redirects=False).status_code==303
page=owner.get(base+'/admin/promotions');card=re.search(r'<article class="promo-card">(?:(?!</article>).)*LIFECYCLE(?:(?!</article>).)*</article>',page.text,re.S).group(0)
id=re.search(r'name="id" value="(\d+)"',card).group(1)
assert 'Code privé' in card and 'Suspendre' in card
assert owner.post(base+'/admin/promotions/visibility',data={'csrf':csrf,'id':id,'is_public':1},allow_redirects=False).status_code==303
assert owner.post(base+'/admin/promotions/toggle',data={'csrf':csrf,'id':id,'enabled':0},allow_redirects=False).status_code==303
page=owner.get(base+'/admin/promotions');card=re.search(r'<article class="promo-card">(?:(?!</article>).)*LIFECYCLE(?:(?!</article>).)*</article>',page.text,re.S).group(0)
assert 'Visible dans les offres publiques' in card and '>Activer<' in card
assert owner.post(base+'/admin/promotions/remove',data={'csrf':csrf,'id':id,'action':'archive'}).status_code==409
assert owner.post(base+'/admin/promotions/remove',data={'csrf':'bad','id':id,'action':'delete'}).status_code==403
assert owner.post(base+'/admin/promotions/remove',data={'csrf':csrf,'id':id,'action':'delete'},allow_redirects=False).status_code==303
assert 'LIFECYCLE' not in owner.get(base+'/admin/promotions').text
page=owner.get(base+'/admin/promotions');card=re.search(r'<article class="promo-card">(?:(?!</article>).)*TEST200(?:(?!</article>).)*</article>',page.text,re.S).group(0);id=re.search(r'name="id" value="(\d+)"',card).group(1)
assert owner.post(base+'/admin/promotions/remove',data={'csrf':csrf,'id':id,'action':'delete'}).status_code==409
assert owner.post(base+'/admin/promotions/remove',data={'csrf':csrf,'id':id,'action':'archive'}).status_code==409
assert owner.post(base+'/admin/promotions/toggle',data={'csrf':csrf,'id':id,'enabled':0},allow_redirects=False).status_code==303
assert owner.post(base+'/admin/promotions/remove',data={'csrf':csrf,'id':id,'action':'archive'},allow_redirects=False).status_code==303
assert 'TEST200' not in owner.get(base+'/admin/promotions').text
assert 'TEST200' in owner.get(base+'/admin/promotions?archived=1').text
assert owner.post(base+'/admin/promotions/toggle',data={'csrf':csrf,'id':id,'enabled':1},allow_redirects=False).status_code==303
preview=requests.post(base+'/promotions/preview',json={'code':'TEST200','phone':'0700000009','battery_id':1,'station_code':'TEST01'})
assert preview.status_code==422,preview.text
assert 'indisponible' in preview.json()['error']
print('Promotion HTTP: independent activation/public visibility, CSRF, unused deletion, used inactive archive and archived preview rejection OK')
page=owner.get(base+'/admin/promotions?archived=1');csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1)
assert owner.post(base+'/admin/promotions/limits',data={'csrf':csrf,'id':id,'max_uses':5,'max_uses_per_phone':2}).status_code==409
payload={'csrf':csrf,'code':'LIMITS200','kind':'campaign','discount':200,'max_uses':10,'max_uses_per_phone':3,'minimum_completed':0,'start':'2020-01-01','end':'2099-12-31','enabled':'on'}
assert owner.post(base+'/admin/promotions',data=payload,allow_redirects=False).status_code==303
page=owner.get(base+'/admin/promotions');card=re.search(r'<article class="promo-card">(?:(?!</article>).)*LIMITS200(?:(?!</article>).)*</article>',page.text,re.S).group(0);id=re.search(r'name="id" value="(\d+)"',card).group(1)
assert '3 utilisation(s) autorisée(s) par numéro' in card
assert owner.post(base+'/admin/promotions/limits',data={'csrf':'bad','id':id,'max_uses':5,'max_uses_per_phone':2}).status_code==403
assert reader.post(base+'/admin/promotions/limits',data={'csrf':csrf,'id':id,'max_uses':5,'max_uses_per_phone':2}).status_code==403
assert owner.post(base+'/admin/promotions/limits',data={'csrf':csrf,'id':id,'max_uses':5,'max_uses_per_phone':0}).status_code==422
assert owner.post(base+'/admin/promotions/limits',data={'csrf':csrf,'id':id,'max_uses':5,'max_uses_per_phone':2},allow_redirects=False).status_code==303
page=owner.get(base+'/admin/promotions');card=re.search(r'<article class="promo-card">(?:(?!</article>).)*LIMITS200(?:(?!</article>).)*</article>',page.text,re.S).group(0);assert '2 utilisation(s) autorisée(s) par numéro' in card
print('Promotion limit settings HTTP: create, edit, positive bounds, archive rejection, CSRF and permissions OK')
# Station pricing settings and public promo preview use the same effective price.
page=owner.get(base+'/admin/pricing');assert page.status_code==200,page.text
csrf=re.search(r'name="csrf" value="([^"]+)"',page.text).group(1)
price={'csrf':csrf,'pricing_scope':'selected','stations[]':'TEST01','rental_fee':250,'default_deposit':1200,'duration_minutes':30,'late_percent':10,'deposit_enabled':1,'grace_minutes':2}
assert owner.post(base+'/admin/prices',data=price,allow_redirects=False).status_code==303
page=owner.get(base+'/admin/pricing?station=TEST01');assert page.status_code==200 and 'value="250"' in page.text and 'Tarif spécifique' in page.text
rent=requests.get(base+'/rent?station=TEST01');assert rent.status_code==200 and 'window.TB_PRICE = 250' in rent.text,rent.text
preview=requests.post(base+'/promotions/preview',json={'code':'LIMITS200','phone':'0700000077','station_code':'TEST01','battery_id':1});assert preview.status_code==200,preview.text;assert preview.json()['fee']==250 and preview.json()['deposit']==1000
bad=dict(price);bad['csrf']='bad';assert owner.post(base+'/admin/prices',data=bad).status_code==403
assert reader.post(base+'/admin/prices',data=price).status_code==403
bad=dict(price);bad['stations[]']='UNKNOWN';assert owner.post(base+'/admin/prices',data=bad).status_code==422
bad=dict(price);bad.pop('stations[]');assert owner.post(base+'/admin/prices',data=bad).status_code==422
price['deposit_enabled']=0;assert owner.post(base+'/admin/prices',data=price,allow_redirects=False).status_code==303
preview=requests.post(base+'/promotions/preview',json={'code':'LIMITS200','phone':'0700000077','station_code':'TEST01','battery_id':1});assert preview.status_code==422 and 'caution' in preview.json()['error'],preview.text
assert owner.post(base+'/admin/prices',data={'csrf':csrf,'pricing_scope':'inherit','stations[]':'TEST01'},allow_redirects=False).status_code==303
page=owner.get(base+'/admin/pricing?station=TEST01');assert 'Tarif spécifique' not in page.text
assert owner.get(base+'/admin/pricing?station=UNKNOWN').status_code==404
print('Station pricing HTTP: selected station, public rent amount, promo deposit, disabled caution, inheritance, CSRF and permissions OK')
