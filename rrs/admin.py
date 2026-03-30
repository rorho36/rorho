from django.contrib import admin
from .models import DashboardProgress, Trade


@admin.register(DashboardProgress)
class DashboardProgressAdmin(admin.ModelAdmin):
    list_display = ('current_amount', 'goal_amount', 'updated_at')
    readonly_fields = ('goal_amount', 'updated_at')


@admin.register(Trade)
class TradeAdmin(admin.ModelAdmin):
    list_display = ('date', 'amount', 'firm', 'action', 'created_at')
    list_filter = ('date', 'firm')
    search_fields = ('firm', 'action')
