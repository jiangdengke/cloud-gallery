FROM nginx:alpine

# 复制构建好的前端文件 (前提：宿主机已有 web/dist)
# 注意：docker-compose build context 是项目根目录
COPY web/dist /usr/share/nginx/html

# 复制 Nginx 配置
COPY docker/nginx/frontend.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
