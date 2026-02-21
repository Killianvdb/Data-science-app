c = open('/app/data_cleaner.py','r',encoding='utf-8').read()
c = c.replace(
    "s.apply(lambda v: v.replace(',', '.') if v.count(',') == 1 and '.' not in v else v.replace(',', ''))",
    "s.apply(lambda v: str(v).replace(',', '.') if str(v).count(',') == 1 and '.' not in str(v) else str(v).replace(',', ''))"
)
open('/app/data_cleaner.py','w',encoding='utf-8').write(c)
print('OK')