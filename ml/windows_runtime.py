"""Small runtime guards for Python processes started by Laravel on Windows.

Some Windows/PHP development environments cannot initialize the OS crypto
provider in child processes.  Python's multiprocessing module requests an
authentication key during import, even though this project deliberately runs
the classifier single-threaded.  Keep the normal secure provider whenever it
works and install a process-local fallback only for that specific failure.
"""

import os
import inspect
import random
import sys
import threading
import time
import types


def install_windows_runtime_guards():
    if os.name != "nt":
        return

    if os.environ.get("VEHISIGHTS_ML_NO_ASYNCIO") == "1":
        # Joblib imports asyncio only to test whether a cached return value is
        # awaitable. Importing the full Windows asyncio implementation loads
        # _overlapped/Winsock, which is unavailable to some PHP child
        # processes. The prediction worker never performs asynchronous I/O,
        # so this deliberately tiny process-local module is sufficient.
        asyncio_stub = types.ModuleType("asyncio")
        asyncio_stub.iscoroutine = inspect.isawaitable
        sys.modules["asyncio"] = asyncio_stub

    original_urandom = os.urandom

    try:
        original_urandom(1)
        return
    except OSError:
        pass

    generator = random.Random(
        (time.time_ns() << 16) ^ os.getpid() ^ id(original_urandom)
    )
    lock = threading.Lock()

    def process_local_urandom(size):
        try:
            return original_urandom(size)
        except OSError:
            with lock:
                return bytes(generator.getrandbits(8) for _ in range(size))

    os.urandom = process_local_urandom
    # ``random`` caches os.urandom when the module is imported. NumPy reaches
    # that cached reference while scikit-learn is loading, so update it too.
    random._urandom = process_local_urandom

