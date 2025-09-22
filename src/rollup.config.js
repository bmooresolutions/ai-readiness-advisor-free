import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import replace from '@rollup/plugin-replace';
import { terser } from 'rollup-plugin-terser';
import postcss from 'rollup-plugin-postcss';


export default {
input: 'src/admin/index.js',
output: {
file: 'assets/admin.js',
format: 'iife', // WordPress-friendly (no module loader required)
name: 'AIRAAdmin',
sourcemap: true
},
plugins: [
replace({
'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production'),
preventAssignment: true
}),
resolve(),
commonjs(),
postcss({
extract: 'assets/admin.css',
minimize: true
}),
terser()
]
};
