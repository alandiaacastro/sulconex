#!/bin/sh
rm -Rf /tmp/build/
mkdir /tmp/build/

cd ~/git/adianti/framework/
git archive --format=tar.gz --prefix=framework/ HEAD > /tmp/build/framework.tgz

cd ~/git/adianti/template/
git archive --format=tar.gz --prefix=template/ HEAD > /tmp/build/template.tgz

cd /tmp/build/
tar -xzvf template.tgz
tar -xzvf framework.tgz

cp -R framework/lib template
cp -R framework/vendor template

chmod 777 framework/app/database -R
chmod 777 template/app/database -R

chmod 777 framework/app/output -R
chmod 777 template/app/output -R

chmod 777 framework/tmp -R
chmod 777 template/tmp -R

rm template/build.sh
rm template/app/database/build.sh

zip -r framework-final.zip framework
zip -r template-super-final.zip template

rm template/app/control/communication/calendar -R
rm template/app/control/communication/documents -R
rm template/app/control/communication/messages -R
rm template/app/control/communication/pages -R
rm template/app/control/communication/posts -R
rm template/app/control/communication/schedule -R
rm template/app/control/communication/tasks -R

rm template/app/model/communication/calendar -R
rm template/app/model/communication/documents -R
rm template/app/model/communication/messages -R
rm template/app/model/communication/pages -R
rm template/app/model/communication/posts -R
rm template/app/model/communication/schedule -R
rm template/app/model/communication/tasks -R

mv template/menu-basic.xml template/menu.xml
mv template/app/config/application-basic.php template/app/config/application.php
mv template/app/templates/adminbs5/layout-basic.html template/app/templates/adminbs5/layout.html

cd template/app/database
mv communication-basic.db communication.db
mv permission-basic.db permission.db
rm *super*

cd ../../..

zip -r template-basic-final.zip template

rm framework.tgz
rm template.tgz
rm framework -Rf
rm template -Rf

echo ""
echo "Build pronto em /tmp/bulid"
echo ""
