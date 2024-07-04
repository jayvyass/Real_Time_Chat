import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import tailwindcss from "tailwindcss";
import vue from '@vitejs/plugin-vue';


export default defineConfig({
    plugins: [
        laravel({
            input: [
                "themes/tailwind/css/app.css",
                "themes/tailwind/js/app.js"
            ],
            buildDirectory: "tailwind",
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        
        {
            name: "blade",
            handleHotUpdate({ file, server }) {
                if (file.endsWith(".blade.php")) {
                    server.ws.send({
                        type: "full-reload",
                        path: "*",
                    });
                }
            },
        },
    ],
    server: {
        host: '192.168.0.116', // Your local IP address
        port: 5173, // The port Vite is using
        hmr: {
            host: '192.168.0.116', // Your local IP address
        },
    },
    resolve: {
        alias: {
            '@': '/themes/tailwind/js',
            
        }
    },
    css: {
        postcss: {
            plugins: [
                tailwindcss({
                    config: path.resolve(__dirname, "tailwind.config.js"),
                }),
            ],
        },
    },
});
