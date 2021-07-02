crossfilter_version=1.5.4
# Dernière version avec quicksort (utilisé par c3):
crossfilter_version=1.4.8
# Ne pas confondre c3 de C3JS et c3 de drarmstr:
c3_version=0.7.20 # C3JS
c3_version=0.1.6 # drarmstr

all: libs

libs: www/lib www/lib/crossfilter.js www/lib/c3

www/lib:
	mkdir -p $@

www/lib/crossfilter.js: www/lib/crossfilter-$(crossfilter_version).js
	rm -f $@
	ln -s `basename $<` $@

www/lib/crossfilter-$(crossfilter_version).js: /tmp/crossfilter-$(crossfilter_version).tgz
	cd /tmp/ && tar xzf $<
	mv /tmp/crossfilter-$(crossfilter_version)/crossfilter.js $@
	touch $@

/tmp/crossfilter-$(crossfilter_version).tgz:
	curl -L -o $@ https://github.com/crossfilter/crossfilter/archive/refs/tags/$(crossfilter_version).tar.gz

# Ne pas confondre c3 de C3JS et c3 de drarmstr:

#www/lib/c3.js: www/lib/c3-$(c3_version).js
#	rm -f $@
#	ln -s `basename $<` $@
#
#www/lib/c3-$(c3_version).js: /tmp/c3-$(c3_version).tgz
#	cd /tmp/ && tar xzf $<
#	mv /tmp/c3-$(c3_version)/c3.js $@
#
#/tmp/c3-$(c3_version).tgz:
#	curl -L -o $@ https://github.com/c3js/c3/archive/refs/tags/v$(c3_version).tar.gz

www/lib/c3: www/lib/c3-$(c3_version)
	rm -f $@
	ln -s `basename $<` $@

www/lib/c3-$(c3_version): /tmp/c3-$(c3_version).tgz
	cd /tmp/ && tar xzf $<
	mv /tmp/c3-$(c3_version)/js $@
	touch $@

/tmp/c3-$(c3_version).tgz:
	curl -L -o $@ https://github.com/drarmstr/c3/archive/refs/tags/$(c3_version).tar.gz
