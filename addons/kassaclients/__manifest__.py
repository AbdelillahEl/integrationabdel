{
    'name': 'KassaClients',
    'version': '1.0',
    'category': 'Custom',
    'summary': 'A custom addon for integration',
    'depends': ['base',],
    'external_dependencies': {'python': ['pika']},
    'data': [
        'security/ir.model.access.csv',
        'views/res_partner_views.xml',
        'views/client_list_view.xml',
       

    ]
    
    
    ,
    'installable': True,
    'application': True,
    "post_init_hook": "post_init_hook_method",

}
