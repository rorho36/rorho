from django.db import models


class DashboardProgress(models.Model):
    goal_amount = models.DecimalField(max_digits=14, decimal_places=2, default=4000000, editable=False)
    current_amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    overall_payouts = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    overall_expenses = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    current_challenges = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    updated_at = models.DateTimeField(auto_now=True)

    def fill_percentage(self):
        if self.goal_amount == 0:
            return 0
        return min(100, (self.current_amount / self.goal_amount) * 100)

    def __str__(self):
        return f"{self.current_amount}/{self.goal_amount}"


class Trade(models.Model):
    date = models.DateField(null=True, blank=True)
    amount = models.DecimalField(max_digits=14, decimal_places=2, null=True, blank=True)
    firm = models.CharField(max_length=255, blank=True)
    action = models.CharField(max_length=50, blank=True)
    existing = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f"{self.date} - {self.firm} - {self.action}"
