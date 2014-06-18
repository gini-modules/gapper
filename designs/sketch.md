
    通过作为app在gapper.in上注册后使用.

    注册为group app


* 本地开发
    * cache: gini cache
    * 创建数据库：mall_brand
    * 更新数据表: gini update orm
    * 静态文件: gini update web
    * 模拟RPC: 去掉class/Gini/Module/MallBrand.php中tests/mocking/RPC.php的注释
    * 七牛云配置：raw/config/cloud.yml

    七牛的bucket设置为public

        ---
        default: qiniu
        qiniu:
            bucket: app-brand
            accessKey: YOURACCESSKEY
            secretKey: YOURSECRETKEY
        ...

    * 在gapper注册, 并记下client_id、client_secret，提供给其他app使用

            gini gapper app create

            生成mall-brand的注册信息，将信息以brand.yml的方式存储在mall-vendor的raw/config目录下
            供mall-vendor可以rpc调用mall-brand提供的服务



* ORM: 
    * brand
        * name: 品牌名称
        * abbr: 品牌名称简称
        * company: 生产商
        * image: 品牌图片
        * verified: 审核信息
        * …


* CGI
    * POST: ajax/add
        * 品牌名称
        * 品牌图片
        * return:
            * true
            * error object
    * POST: ajax/update
        * id
        * 品牌名称
        * 品牌图片
        * return:
            * true
            * error object
    * POST: ajax/delete
        * id
        * return:
            * true
            * error object
    * GET: ajax/list
        * GET
        * perpage: 每页显示记录数
        * pn: 当前第几页
        * filter: 关键字
    * GET: list
        * 仅用来加载模板，数据通过异步方式加载


* API
    * GET: /search
        * $rpc->mall->brand->search($params)
            * $params
                * perpage: 每页显示记录数
                * pn: 当前第几页
                * name: 模糊查询品牌名
                * abbr: 模糊查询品牌简称
                * alias: 模糊查询品牌别名
                * type: 类别条件限制
        * return
            * 空: 没有符合条件的
            * brand的数组： []
    * GET: /getInfo
        * $rpc->mall->brand->getInfo($name)
            * $name: 品牌名
        * return
            * 出错时的错误信息
                * code:
                * message:
            * 品牌信息数组
    * GET: /getTypes
        * $rpc->mall->brand->getTypes
        * return
            * 出错时的错误信息
                * code:
                * message:
            * 类别的数组: [string,string,string]
            
    * POST: /setBrandAlias
        * $rpc->mall->brand->setBrandAlias($name)
            * $name: 别名名称
        * return
            * 出错时的错误信息
                * code:
                * message:
            * 保存结果：true | false


