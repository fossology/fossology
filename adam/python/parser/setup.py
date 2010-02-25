from distutils.core import setup
from distutils.extension import Extension
from Pyrex.Distutils import build_ext
setup(
  name = "PyrexTokenizer",
  ext_modules=[ 
    Extension("libfossparser", ["libfossparser.pyx"], extra_compile_args=['-O3'])
    ],
  cmdclass = {'build_ext': build_ext}
)

