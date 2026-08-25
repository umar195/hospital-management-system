#!/usr/bin/env bash
set -u
BASE="http://127.0.0.1:8080"; C=".ft_cookie"; rm -f $C
tok(){ curl -s -b $C -c $C "$BASE$1" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
q(){ mysql -uroot -proot hospital_db -N -B -e "$1" 2>/dev/null; }
T=$(tok /login.php); curl -s -b $C -c $C -o /dev/null -d "csrf_token=$T&username=admin&pass=admin123" "$BASE/login.php"
T=$(tok /modules/walkin/index.php)
curl -s -b $C -c $C -o /dev/null -L --data-urlencode "csrf_token=$T" --data-urlencode "patient_mode=new" \
 --data-urlencode "new_full_name=Post Review Patient" --data-urlencode "new_gender=Female" --data-urlencode "new_age=30" \
 --data-urlencode "new_phone=03005550000" --data-urlencode "tests[]=2" --data-urlencode "discount=0" \
 --data-urlencode "paid_amount=200" --data-urlencode "payment_method=Cash" "$BASE/modules/walkin/index.php"
PID=$(q "SELECT id FROM patients WHERE full_name='Post Review Patient'")
OID=$(q "SELECT id FROM test_orders WHERE patient_id=$PID")
ITEM=$(q "SELECT id FROM test_order_items WHERE order_id=$OID")
echo "patient=$PID order=$OID item=$ITEM paid=$(q "SELECT paid_amount FROM test_orders WHERE id=$OID")"
T=$(tok "/modules/orders/results.php?id=$OID")
curl -s -b $C -c $C -o /dev/null -L --data-urlencode "csrf_token=$T" --data-urlencode "id=$OID" \
 --data-urlencode "item_id[]=$ITEM" --data-urlencode "result_id[]=" --data-urlencode "parameter_id[]=" \
 --data-urlencode "parameter_name[]=Hemoglobin (Hb)" --data-urlencode "unit[]=g/dL" \
 --data-urlencode "reference_range[]=12-15.5" --data-urlencode "value[]=9.0" --data-urlencode "flag[]=auto" \
 --data-urlencode "note[]=" --data-urlencode "complete_item[]=$ITEM" "$BASE/modules/orders/results.php"
echo "flag=$(q "SELECT flag FROM test_results WHERE order_item_id=$ITEM")"
T=$(tok "/modules/orders/view.php?id=$OID")
curl -s -b $C -c $C -o /dev/null -L --data-urlencode "csrf_token=$T" --data-urlencode "id=$OID" --data-urlencode "action=generate_report" "$BASE/modules/orders/view.php"
RID=$(q "SELECT id FROM reports WHERE order_id=$OID")
echo "report=$RID authorized=$(q "SELECT authorized_by FROM reports WHERE id=$RID")"
T=$(tok "/modules/patients/delete.php?id=$PID")
curl -s -b $C -c $C -o /dev/null -L --data-urlencode "csrf_token=$T" --data-urlencode "id=$PID" "$BASE/modules/patients/delete.php"
echo "deleted=$(q "SELECT COUNT(*) FROM patients WHERE id=$PID")"
rm -f $C
