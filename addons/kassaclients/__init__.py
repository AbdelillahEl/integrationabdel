from . import models

def post_init_hook_method(env):
    print("KassaClients addon loaded successfully!")
    env["res.partner"].start()